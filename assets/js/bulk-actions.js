/**
 * WP Folder Boss — Bulk Actions & Move to Folder
 *
 * Hooks into the WordPress bulk actions system and adds
 * "Move to Folder" capability for list tables and media grid.
 */
/* global wpfbData, wpfbTree */

( function () {
	'use strict';

	let selectedFolderId = null;

	/**
	 * REST API call helper.
	 *
	 * @param {string} method
	 * @param {string} path
	 * @param {object} body
	 * @returns {Promise}
	 */
	function api( method, path, body ) {
		return fetch( wpfbData.restUrl + path, {
			method,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpfbData.nonce,
			},
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( ( r ) => r.json() );
	}

	// ------------------------------------------------------------------ //
	//  List table integration — add "Move to Folder" bulk action          //
	// ------------------------------------------------------------------ //

	/**
	 * Add "Move to Folder" to bulk action dropdowns on list table screens.
	 */
	function addBulkActionToListTable() {
		document.querySelectorAll( 'select[name="action"], select[name="action2"]' ).forEach( ( select ) => {
			if ( select.querySelector( 'option[value="wpfb_move"]' ) ) return;

			const opt   = document.createElement( 'option' );
			opt.value   = 'wpfb_move';
			opt.textContent = wpfbData.i18n.moveToFolder;
			select.appendChild( opt );
		} );
	}

	/**
	 * Intercept the bulk action form submission for "wpfb_move".
	 */
	function interceptBulkActionForm() {
		const form = document.getElementById( 'posts-filter' )
			|| document.getElementById( 'media-grid-view-select' )
			|| document.querySelector( '.wp-list-table' )?.closest( 'form' );

		if ( ! form ) return;

		form.addEventListener( 'submit', ( e ) => {
			const action = form.querySelector( 'select[name="action"]' );
			const action2 = form.querySelector( 'select[name="action2"]' );
			const value   = ( action && action.value ) || ( action2 && action2.value );

			if ( value !== 'wpfb_move' ) return;

			e.preventDefault();

			// Collect checked items.
			const ids = [];
			form.querySelectorAll( 'input[type="checkbox"]:checked[name="post[]"]' ).forEach( ( cb ) => {
				const id = parseInt( cb.value, 10 );
				if ( id ) ids.push( id );
			} );

			if ( ! ids.length ) return;

			openFolderModal( ( folderId ) => {
				bulkMove( ids, folderId, 'post' );
			} );
		} );
	}

	// ------------------------------------------------------------------ //
	//  Media grid — floating action bar when items are selected           //
	// ------------------------------------------------------------------ //

	/**
	 * Create and inject a floating "Move to Folder" bar for the media grid.
	 */
	function createFloatingBar() {
		if ( document.getElementById( 'wpfb-float-bar' ) ) return;

		const bar       = document.createElement( 'div' );
		bar.id          = 'wpfb-float-bar';
		bar.className   = 'wpfb-float-bar';
		bar.style.display = 'none';
		bar.innerHTML   = `<span class="wpfb-float-label"></span>
			<button type="button" class="button button-primary" id="wpfb-float-move">${ escHtml( wpfbData.i18n.moveToFolder ) }</button>`;

		document.body.appendChild( bar );

		document.getElementById( 'wpfb-float-move' ).addEventListener( 'click', () => {
			const ids = getSelectedMediaIds();
			if ( ! ids.length ) return;

			openFolderModal( ( folderId ) => {
				bulkMove( ids, folderId, 'post' );
			} );
		} );
	}

	/**
	 * Watch the wp.media selection and show/hide the floating bar.
	 */
	function watchMediaSelection() {
		if ( ! window.wp || ! wp.media ) return;

		// Wait until the media frame is ready.
		const waitForFrame = setInterval( () => {
			if ( ! wp.media.frame ) return;
			clearInterval( waitForFrame );

			wp.media.frame.on( 'open', () => {
				const selection = wp.media.frame.state().get( 'selection' );
				if ( ! selection ) return;

				selection.on( 'add remove reset', () => {
					updateFloatingBar( selection.length );
				} );
			} );
		}, 200 );
	}

	/**
	 * Update visibility and label of the floating bar.
	 *
	 * @param {number} count
	 */
	function updateFloatingBar( count ) {
		const bar = document.getElementById( 'wpfb-float-bar' );
		if ( ! bar ) return;

		if ( count > 0 ) {
			bar.style.display = '';
			const label = bar.querySelector( '.wpfb-float-label' );
			if ( label ) {
				label.textContent = count + ' ' + ( count === 1 ? 'item' : 'items' ) + ' selected';
			}
		} else {
			bar.style.display = 'none';
		}
	}

	/**
	 * Get currently selected attachment IDs from the media grid.
	 *
	 * @returns {number[]}
	 */
	function getSelectedMediaIds() {
		const ids = [];
		if ( window.wp && wp.media && wp.media.frame ) {
			const selection = wp.media.frame.state().get( 'selection' );
			if ( selection ) {
				selection.each( ( model ) => ids.push( model.id ) );
			}
		}
		return ids;
	}

	// ------------------------------------------------------------------ //
	//  Folder picker modal                                                //
	// ------------------------------------------------------------------ //

	let _moveCallback = null;

	/**
	 * Open the folder picker modal.
	 *
	 * @param {Function} callback Called with folderId when confirmed.
	 */
	function openFolderModal( callback ) {
		_moveCallback = callback;
		selectedFolderId = null;

		const modal = document.getElementById( 'wpfb-folder-modal' );
		if ( ! modal ) return;

		// Populate the modal tree from the sidebar tree.
		const modalTree = modal.querySelector( '#wpfb-modal-tree' );
		const sidebar   = document.getElementById( 'wpfb-folder-tree' );

		if ( modalTree && sidebar ) {
			modalTree.innerHTML = sidebar.innerHTML;

			// Remove toggle buttons and make items selectable.
			modalTree.querySelectorAll( '.wpfb-folder-children' ).forEach( ( ul ) => {
				ul.style.display = '';
			} );
			modalTree.querySelectorAll( '.wpfb-toggle-btn, .wpfb-toggle-placeholder' ).forEach( ( el ) => {
				el.remove();
			} );

			modalTree.addEventListener( 'click', ( e ) => {
				const item = e.target.closest( '.wpfb-folder-item' );
				if ( ! item ) return;

				modalTree.querySelectorAll( '[aria-selected="true"]' ).forEach( ( el ) => {
					el.setAttribute( 'aria-selected', 'false' );
				} );
				item.setAttribute( 'aria-selected', 'true' );
				selectedFolderId = parseInt( item.dataset.id, 10 );
			} );
		}

		modal.style.display = '';
		modal.setAttribute( 'aria-hidden', 'false' );
	}

	/**
	 * Close the folder picker modal.
	 */
	function closeFolderModal() {
		const modal = document.getElementById( 'wpfb-folder-modal' );
		if ( modal ) {
			modal.style.display = 'none';
			modal.setAttribute( 'aria-hidden', 'true' );
		}
		_moveCallback = null;
		selectedFolderId = null;
	}

	// ------------------------------------------------------------------ //
	//  Bulk move execution                                                //
	// ------------------------------------------------------------------ //

	/**
	 * Move items to the selected folder via REST.
	 *
	 * @param {number[]} ids
	 * @param {number}   folderId
	 * @param {string}   type
	 */
	function bulkMove( ids, folderId, type ) {
		if ( ! ids.length ) return;

		api( 'POST', '/assign', {
			folder_id : folderId,
			item_ids  : ids,
			item_type : type,
		} ).then( () => {
			document.dispatchEvent(
				new CustomEvent( 'wpfb:items-moved', { detail: { folderId, ids, type } } )
			);

			// Reload the current view to reflect changes.
			if ( window.wp && wp.media && wp.media.frame ) {
				wp.media.frame.content.get().collection.props.trigger( 'change' );
			} else {
				window.location.reload();
			}
		} ).catch( () => {} );
	}

	/**
	 * Simple HTML escape.
	 *
	 * @param {string} str
	 * @returns {string}
	 */
	function escHtml( str ) {
		const d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( str ) );
		return d.innerHTML;
	}

	/**
	 * Initialize bulk actions integration.
	 */
	function init() {
		addBulkActionToListTable();
		interceptBulkActionForm();

		// Media grid floating bar.
		createFloatingBar();
		watchMediaSelection();

		// Modal confirm/cancel.
		const confirmBtn = document.getElementById( 'wpfb-modal-confirm' );
		const cancelBtn  = document.getElementById( 'wpfb-modal-cancel' );

		if ( confirmBtn ) {
			confirmBtn.addEventListener( 'click', () => {
				if ( selectedFolderId !== null && _moveCallback ) {
					_moveCallback( selectedFolderId );
				}
				closeFolderModal();
			} );
		}

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', closeFolderModal );
		}

		// Close modal on backdrop click.
		const modal = document.getElementById( 'wpfb-folder-modal' );
		if ( modal ) {
			modal.addEventListener( 'click', ( e ) => {
				if ( e.target === modal ) closeFolderModal();
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
