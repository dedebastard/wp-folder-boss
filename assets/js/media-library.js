/**
 * WP Folder Boss — Media Library Grid Integration
 */
/* global wpfbData, wpfbMediaData */

( function ( $ ) {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.media ) {
		return;
	}

	var activeFolderId = -1;
	var sidebarInjected = false;

	/* --------------------------------------------------------- */
	/*  1. Inject sidebar into the grid view via polling          */
	/* --------------------------------------------------------- */

	function injectSidebar() {
		if ( sidebarInjected ) return true;

		var sidebar = document.getElementById( 'wpfb-sidebar' );
		if ( ! sidebar ) return false;

		// Target: .media-frame-content is the container WP uses for the grid
		var target = document.querySelector( '.media-frame-content' );
		if ( ! target ) return false;

		// Make target a flex row so sidebar sits on the left
		target.style.display   = 'flex';
		target.style.flexDirection = 'row';

		// Move sidebar inside as first child
		target.insertBefore( sidebar, target.firstChild );
		sidebar.style.display = '';
		sidebar.style.position = 'relative';
		sidebar.style.height   = 'auto';
		sidebar.style.minHeight = '100%';
		sidebar.style.flexShrink = '0';
		sidebar.style.zIndex   = '1';

		// Make the attachments browser fill the remaining space
		var browser = target.querySelector( '.attachments-browser' );
		if ( browser ) {
			browser.style.flex     = '1';
			browser.style.minWidth = '0';
		}

		sidebarInjected = true;

		// Re-init tree if needed (sidebar was hidden, tree may not have initialized)
		if ( typeof window.wpfbTree === 'undefined' || ! window.wpfbTree ) {
			var sidebarEl = document.getElementById( 'wpfb-sidebar' );
			if ( sidebarEl && typeof window.WPFBTreeInit === 'function' ) {
				window.WPFBTreeInit( sidebarEl );
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

				// WP sends filters inside options.data.query for the grid AJAX
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

		// Refresh the media grid
		if ( wp.media.frame ) {
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

	// Extra delayed attempts for slow-loading frames
	setTimeout( pollForSidebar, 500 );
	setTimeout( pollForSidebar, 1500 );
	setTimeout( pollForSidebar, 3000 );
	setTimeout( pollForSidebar, 5000 );

} )( jQuery );
