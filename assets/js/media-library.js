/**
 * WP Folder Boss — Media Library Grid Integration
 */
/* global wpfbData, wpfbMediaData, jQuery */

( function ( $ ) {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.media ) {
		return;
	}

	var activeFolderId = -1;
	var sidebarInjected = false;

	/* --------------------------------------------------------- */
	/*  1. Inject sidebar into the grid view                     */
	/* --------------------------------------------------------- */

	function injectSidebar() {
		if ( sidebarInjected ) return true;

		var sidebar = document.getElementById( 'wpfb-sidebar' );
		if ( ! sidebar ) return false;

		// WordPress grid view structure on upload.php:
		//   #wpbody-content > .wrap
		//     > h1
		//     > .wp-filter (the filter/search bar)
		//     > .media-frame (the grid frame)
		//       > .media-frame-content
		//         > .attachments-browser
		//           > .media-toolbar
		//           > ul.attachments
		//           > .media-sidebar

		// Try multiple possible targets in order of specificity
		var target = null;
		var mode = 'none';

		// Option 1: The main page wrapper (.wrap) — we'll put sidebar beside everything
		var wrap = document.querySelector( 'body.upload-php .wrap' );
		var mediaFrame = document.querySelector( '.media-frame' );

		if ( wrap && mediaFrame ) {
			target = wrap;
			mode = 'wrap';
		}

		if ( ! target ) return false;

		if ( mode === 'wrap' ) {
			// Strategy: Put sidebar as first child of .wrap using flexbox
			// This works because .wrap contains EVERYTHING (title, filters, grid)
			wrap.style.display = 'flex';
			wrap.style.flexWrap = 'nowrap';
			wrap.style.alignItems = 'flex-start';

			// Create a container for all the non-sidebar content
			var contentWrapper = document.getElementById( 'wpfb-content-wrapper' );
			if ( ! contentWrapper ) {
				contentWrapper = document.createElement( 'div' );
				contentWrapper.id = 'wpfb-content-wrapper';
				contentWrapper.style.flex = '1';
				contentWrapper.style.minWidth = '0';
				contentWrapper.style.width = '0'; // Force flex to control width

				// Move all existing children of .wrap into contentWrapper
				while ( wrap.firstChild ) {
					// Don't move the sidebar itself if it's already in .wrap
					if ( wrap.firstChild === sidebar ) {
						wrap.removeChild( sidebar );
						continue;
					}
					contentWrapper.appendChild( wrap.firstChild );
				}

				wrap.appendChild( contentWrapper );
			}

			// Insert sidebar before contentWrapper
			sidebar.style.display = '';
			sidebar.style.position = 'sticky';
			sidebar.style.top = '32px'; // Below WP admin bar
			sidebar.style.height = 'calc(100vh - 32px)';
			sidebar.style.flexShrink = '0';
			sidebar.style.overflowY = 'auto';
			sidebar.style.zIndex = '1';

			wrap.insertBefore( sidebar, wrap.firstChild );
		}

		sidebarInjected = true;

		// Re-init tree if it wasn't initialized (sidebar was hidden before)
		if ( typeof window.wpfbTree === 'undefined' || ! window.wpfbTree ) {
			if ( typeof window.WPFBTreeInit === 'function' ) {
				window.WPFBTreeInit( sidebar );
			}
		}

		return true;
	}

	function pollForSidebar() {
		if ( injectSidebar() ) return;
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( injectSidebar() || attempts > 200 ) {
				clearInterval( interval );
			}
		}, 100 );
	}

	/* --------------------------------------------------------- */
	/*  2. Pass wpfb_folder to the AJAX query                    */
	/* --------------------------------------------------------- */

	if ( wp.media.model && wp.media.model.Query ) {
		var OrigSync = wp.media.model.Query.prototype.sync;

		wp.media.model.Query.prototype.sync = function ( method, model, options ) {
			if ( 'read' === method && activeFolderId !== -1 ) {
				options = options || {};
				options.data = options.data || {};

				if ( typeof options.data.query === 'object' && options.data.query !== null ) {
					options.data.query.wpfb_folder = activeFolderId;
				} else {
					options.data.wpfb_folder = activeFolderId;
				}
			}
			return OrigSync.call( this, method, model, options );
		};
	}

	/* --------------------------------------------------------- */
	/*  3. Listen for folder selection and refresh grid           */
	/* --------------------------------------------------------- */

	document.addEventListener( 'wpfb:folder-selected', function ( e ) {
		if ( e.detail.context !== 'media' ) return;
		activeFolderId = parseInt( e.detail.folderId, 10 );

		// Check if we're in grid mode — if so, refresh via wp.media
		var isGridMode = document.querySelector( '.media-frame' ) !== null
			&& document.querySelector( '.wp-list-table.media' ) === null;

		if ( isGridMode && wp.media.frame ) {
			try {
				var content = wp.media.frame.content.get();
				if ( content && content.collection ) {
					if ( activeFolderId === -1 ) {
						content.collection.props.unset( 'wpfb_folder' );
					} else {
						content.collection.props.set( 'wpfb_folder', activeFolderId );
					}
					content.collection.reset();
					content.collection.more();
				}
			} catch ( err ) {}
		}
	} );

	/* --------------------------------------------------------- */
	/*  4. Auto-assign uploads to active folder                  */
	/* --------------------------------------------------------- */

	if ( wp.Uploader ) {
		var origInit = wp.Uploader.prototype.init;

		wp.Uploader.prototype.init = function () {
			if ( origInit ) {
				origInit.apply( this, arguments );
			}

			this.uploader.bind( 'FileUploaded', function ( up, file, response ) {
				if ( activeFolderId <= 0 ) return;

				var parsed;
				try {
					parsed = JSON.parse( response.response );
				} catch ( e ) {
					return;
				}

				var attachmentId = parsed && parsed.id;
				if ( ! attachmentId ) return;

				fetch( wpfbData.restUrl + '/assign', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': wpfbData.nonce,
					},
					body: JSON.stringify( {
						folder_id: activeFolderId,
						item_ids: [ attachmentId ],
						item_type: 'post',
					} ),
				} ).catch( function () {} );
			} );
		};
	}

	/* --------------------------------------------------------- */
	/*  5. Refresh grid when items are moved                     */
	/* --------------------------------------------------------- */

	document.addEventListener( 'wpfb:items-moved', function () {
		if ( wp.media && wp.media.frame ) {
			try {
				var content = wp.media.frame.content.get();
				if ( content && content.collection ) {
					content.collection.reset();
					content.collection.more();
				}
			} catch ( e ) {}
		}
	} );

	/* --------------------------------------------------------- */
	/*  6. Start polling                                         */
	/* --------------------------------------------------------- */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', pollForSidebar );
	} else {
		pollForSidebar();
	}

	setTimeout( pollForSidebar, 500 );
	setTimeout( pollForSidebar, 1500 );
	setTimeout( pollForSidebar, 3000 );
	setTimeout( pollForSidebar, 5000 );

} )( jQuery );
