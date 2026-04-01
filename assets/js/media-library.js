/**
 * WP Folder Boss — Media Library Grid Integration
 *
 * Integrates with wp.media to:
 *  1. Add the folder sidebar to the media grid (AttachmentsBrowser).
 *  2. Filter attachments by selected folder.
 *  3. Auto-assign newly uploaded files to the active folder.
 */
/* global wpfbData, wpfbMediaData */

( function ( $ ) {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.media ) {
		return;
	}

	// Active folder ID (-1 = all items).
	var activeFolderId = -1;

	// ------------------------------------------------------------------ //
	//  Inject sidebar into grid view using polling                        //
	// ------------------------------------------------------------------ //

	function injectSidebar() {
		var sidebar = document.getElementById( 'wpfb-sidebar' );
		if ( ! sidebar ) {
			return false;
		}

		// Already injected?
		if ( sidebar.getAttribute( 'data-wpfb-injected' ) === '1' ) {
			return true;
		}

		// Find the media frame content area (grid view target)
		var target = document.querySelector( '.media-frame-content' );

		if ( ! target ) {
			return false;
		}

		// Make the target a flex container so sidebar sits beside the grid
		target.style.display = 'flex';
		target.style.flexDirection = 'row';
		target.style.position = 'relative';

		// Insert sidebar as first child of .media-frame-content
		target.insertBefore( sidebar, target.firstChild );

		// Show sidebar and mark as injected
		sidebar.style.display = '';
		sidebar.style.position = 'relative';
		sidebar.style.height = 'auto';
		sidebar.style.minHeight = '100%';
		sidebar.style.zIndex = '10';
		sidebar.style.flexShrink = '0';
		sidebar.setAttribute( 'data-wpfb-injected', '1' );

		// Make the attachments browser take remaining space
		var browser = target.querySelector( '.attachments-browser' );
		if ( browser ) {
			browser.style.flex = '1';
			browser.style.minWidth = '0';
		}

		return true;
	}

	/**
	 * Poll until sidebar HTML and target container both exist in DOM.
	 */
	function waitAndInject() {
		if ( injectSidebar() ) {
			return;
		}

		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( injectSidebar() || attempts > 200 ) {
				clearInterval( interval );
			}
		}, 100 );
	}

	// ------------------------------------------------------------------ //
	//  Filter attachments by folder via wp.media.model.Query              //
	// ------------------------------------------------------------------ //

	if ( wp.media.model && wp.media.model.Query ) {
		var OrigSync = wp.media.model.Query.prototype.sync;

		wp.media.model.Query.prototype.sync = function ( method, model, options ) {
			if ( 'read' === method && activeFolderId !== -1 ) {
				options = options || {};
				options.data = options.data || {};

				// WordPress sends grid params inside options.data.query
				if ( typeof options.data.query === 'object' && options.data.query !== null ) {
					options.data.query.wpfb_folder = activeFolderId;
				} else {
					options.data.wpfb_folder = activeFolderId;
				}
			}
			return OrigSync.call( this, method, model, options );
		};
	}

	// ------------------------------------------------------------------ //
	//  Listen for folder selection events from folder-tree.js             //
	// ------------------------------------------------------------------ //

	document.addEventListener( 'wpfb:folder-selected', function ( e ) {
		if ( ! e.detail || e.detail.context !== 'media' ) {
			return;
		}
		activeFolderId = parseInt( e.detail.folderId, 10 );

		// Refresh the grid collection
		refreshGrid();
	} );

	function refreshGrid() {
		if ( ! wp.media || ! wp.media.frame ) {
			return;
		}
		try {
			var browser = wp.media.frame.content.get();
			if ( browser && browser.collection ) {
				if ( activeFolderId === -1 ) {
					browser.collection.props.unset( 'wpfb_folder' );
				} else {
					browser.collection.props.set( 'wpfb_folder', activeFolderId );
				}
				browser.collection.reset();
				browser.collection.more();
			}
		} catch ( err ) {
			// Frame not ready yet — will apply on next query
		}
	}

	// ------------------------------------------------------------------ //
	//  Auto-assign uploads to the active folder                           //
	// ------------------------------------------------------------------ //

	if ( wp.Uploader ) {
		var origUploaderInit = wp.Uploader.prototype.init;

		wp.Uploader.prototype.init = function () {
			if ( origUploaderInit ) {
				origUploaderInit.apply( this, arguments );
			}

			this.uploader.bind( 'FileUploaded', function ( up, file, response ) {
				if ( activeFolderId <= 0 ) {
					return;
				}

				// WordPress returns the response as a JSON string in response.response
				var parsed;
				try {
					parsed = JSON.parse( response.response );
				} catch ( e ) {
					return;
				}

				var attachmentId = parsed && parsed.id;
				if ( ! attachmentId ) {
					return;
				}

				// Assign the uploaded file to the active folder via REST
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
				});
			}
	}

	// ------------------------------------------------------------------ //
	//  React to items being moved (refresh grid)                          //
	// ------------------------------------------------------------------ //

	document.addEventListener( 'wpfb:items-moved', function () {
		refreshGrid();
	} );

	// ------------------------------------------------------------------ //
	//  Initialize — wait for DOM then start polling                        //
	// ------------------------------------------------------------------ //

	function init() {
		waitAndInject();
		// Additional delayed attempts for slow-loading pages
		setTimeout( waitAndInject, 500 );
		setTimeout( waitAndInject, 1500 );
		setTimeout( waitAndInject, 3000 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )( jQuery );