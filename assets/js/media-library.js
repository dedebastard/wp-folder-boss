/**
 * WP Folder Boss — Media Library Grid Integration
 */
/* global wpfbData, jQuery */

( function ( $ ) {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.media ) {
		return;
	}

	var activeFolderId = -1;
	var injected = false;

	/* --------------------------------------------------------- */
	/*  1. Show grid sidebar with fixed positioning via CSS      */
	/* --------------------------------------------------------- */

	function showGridSidebar() {
		if ( injected ) return true;

		// Only act in grid mode
		var isGridMode = document.querySelector( '.media-frame' ) !== null;
		if ( ! isGridMode ) return false;

		var sidebar = document.getElementById( 'wpfb-grid-sidebar' );
		if ( ! sidebar ) return false;

		// Show the sidebar
		sidebar.style.display = '';
		document.body.classList.add( 'wpfb-has-grid-sidebar' );
		injected = true;

		// Initialize the folder tree on this sidebar
		if ( typeof window.WPFBTreeInit === 'function' ) {
			window.WPFBTreeInit( sidebar );
		}

		return true;
	}

	function poll() {
		if ( showGridSidebar() ) return;
		var attempts = 0;
		var iv = setInterval( function () {
			attempts++;
			if ( showGridSidebar() || attempts > 300 ) {
				clearInterval( iv );
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

		// Grid mode: refresh via wp.media
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
	/*  6. Start polling for grid mode                           */
	/* --------------------------------------------------------- */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', poll );
	} else {
		poll();
	}

	setTimeout( poll, 300 );
	setTimeout( poll, 1000 );
	setTimeout( poll, 2000 );

} )( jQuery );
