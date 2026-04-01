/**
 * WP Folder Boss — Folder Tree Sidebar Component
 *
 * Renders and manages the interactive folder tree sidebar.
 * Fires custom events for integration with other modules.
 */
/* global wpfbData */

( function () {
	'use strict';

	const STORAGE_KEY = 'wpfb_expanded';

	/**
	 * Get expanded folder IDs from localStorage.
	 *
	 * @returns {Set<string>}
	 */
	function getExpanded() {
		try {
			const raw = localStorage.getItem( STORAGE_KEY );
			return new Set( raw ? JSON.parse( raw ) : [] );
		} catch {
			return new Set();
		}
	}

	/**
	 * Persist expanded folder IDs to localStorage.
	 *
	 * @param {Set<string>} expanded
	 */
	function saveExpanded( expanded ) {
		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( [ ...expanded ] ) );
		} catch {
			// Ignore storage errors.
		}
	}

	/**
	 * Make a REST API request.
	 *
	 * @param {string} method  HTTP method.
	 * @param {string} path    Path relative to REST base.
	 * @param {object} [body]  Optional request body.
	 * @returns {Promise<any>}
	 */
	function api( method, path, body ) {
		const opts = {
			method,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpfbData.nonce,
			},
		};

		if ( body ) {
			opts.body = JSON.stringify( body );
		}

		return fetch( wpfbData.restUrl + path, opts ).then( ( r ) => {
			if ( ! r.ok ) {
				return r.json().then( ( err ) => Promise.reject( err ) );
			}
			return r.json();
		} );
	}

	/**
	 * WPFBTree — the main folder tree controller.
	 */
	class WPFBTree {
		/**
		 * @param {HTMLElement} sidebar
		 */
		constructor( sidebar ) {
			this.sidebar     = sidebar;
			this.tree        = sidebar.querySelector( '#wpfb-folder-tree' );
			this.context     = sidebar.dataset.context || 'media';
			this.contextMenu = document.getElementById( 'wpfb-context-menu' );
			this.expanded    = getExpanded();
			this.activeId    = '-1'; // "All Items" by default
			this.ctxTarget   = null; // The folder item that was right-clicked

			this._bindEvents();
			this._restoreExpanded();
			this._selectNode( this.tree.querySelector( '[data-id="-1"]' ) );
		}

		/**
		 * Bind all event listeners on the tree.
		 */
		_bindEvents() {
			// Folder selection (left click on node text).
			this.tree.addEventListener( 'click', ( e ) => {
				const node   = e.target.closest( '.wpfb-folder-node' );
				const toggle = e.target.closest( '.wpfb-toggle-btn' );
				const item   = e.target.closest( '.wpfb-folder-item' );

				if ( ! item ) return;

				if ( toggle ) {
					e.stopPropagation();
					this._toggleItem( item );
					return;
				}

				if ( node ) {
					this._selectNode( item );
				}
			} );

			// Inline rename on double-click.
			this.tree.addEventListener( 'dblclick', ( e ) => {
				const node = e.target.closest( '.wpfb-folder-node' );
				if ( ! node ) return;
				const item = node.closest( '.wpfb-folder-item' );
				if ( ! item || item.classList.contains( 'wpfb-virtual' ) ) return;
				this._startRename( item );
			} );

			// Right-click context menu.
			this.tree.addEventListener( 'contextmenu', ( e ) => {
				e.preventDefault();
				const item = e.target.closest( '.wpfb-folder-item' );
				this.ctxTarget = item || null;
				this._showContextMenu( e.clientX, e.clientY, item );
			} );

			// Add Folder button.
			const addBtn = this.sidebar.querySelector( '.wpfb-add-folder-btn' );
			if ( addBtn ) {
				addBtn.addEventListener( 'click', () => this._promptNewFolder( 0 ) );
			}

			// Context menu actions.
			if ( this.contextMenu ) {
				this.contextMenu.addEventListener( 'click', ( e ) => {
					const li = e.target.closest( '[data-action]' );
					if ( ! li ) return;
					this._handleContextAction( li.dataset.action );
					this._hideContextMenu();
				} );
			}

			// Hide context menu on outside click.
			document.addEventListener( 'click', ( e ) => {
				if ( this.contextMenu && ! this.contextMenu.contains( e.target ) ) {
					this._hideContextMenu();
				}
			} );

			// Hide context menu on Escape.
			document.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' ) {
					this._hideContextMenu();
				}
			} );
		}

		/**
		 * Toggle expand/collapse for a folder item with children.
		 *
		 * @param {HTMLElement} item
		 */
		_toggleItem( item ) {
			const children    = item.querySelector( '.wpfb-folder-children' );
			const toggleBtn   = item.querySelector( '.wpfb-toggle-btn' );
			const id          = item.dataset.id;
			const isExpanded  = item.getAttribute( 'aria-expanded' ) === 'true';

			if ( ! children ) return;

			if ( isExpanded ) {
				children.style.display = 'none';
				item.setAttribute( 'aria-expanded', 'false' );
				if ( toggleBtn ) toggleBtn.innerHTML = '&#9658;';
				this.expanded.delete( id );
			} else {
				children.style.display = '';
				item.setAttribute( 'aria-expanded', 'true' );
				if ( toggleBtn ) toggleBtn.innerHTML = '&#9660;';
				this.expanded.add( id );
			}

			saveExpanded( this.expanded );
		}

		/**
		 * Restore expanded state from localStorage.
		 */
		_restoreExpanded() {
			this.expanded.forEach( ( id ) => {
				const item = this.tree.querySelector( `[data-id="${ CSS.escape( id ) }"]` );
				if ( item ) {
					const children = item.querySelector( '.wpfb-folder-children' );
					const toggleBtn = item.querySelector( '.wpfb-toggle-btn' );
					if ( children ) {
						children.style.display = '';
						item.setAttribute( 'aria-expanded', 'true' );
						if ( toggleBtn ) toggleBtn.innerHTML = '&#9660;';
					}
				}
			} );
		}

		/**
		 * Mark a folder item as selected and fire the filter event.
		 *
		 * @param {HTMLElement|null} item
		 */
		_selectNode( item ) {
			if ( ! item ) return;

			// Deselect all.
			this.tree.querySelectorAll( '[aria-selected="true"]' ).forEach( ( el ) => {
				el.setAttribute( 'aria-selected', 'false' );
			} );

			item.setAttribute( 'aria-selected', 'true' );
			this.activeId = item.dataset.id;

			// Fire custom event so media-library.js and others can react.
			document.dispatchEvent(
				new CustomEvent( 'wpfb:folder-selected', {
					detail: {
						folderId : this.activeId,
						context  : this.context,
					},
				} )
			);
		}

		/**
		 * Show the context menu at the given coordinates.
		 *
		 * @param {number}           x    Client X.
		 * @param {number}           y    Client Y.
		 * @param {HTMLElement|null} item Folder item (null if outside any folder).
		 */
		_showContextMenu( x, y, item ) {
			if ( ! this.contextMenu ) return;

			const isVirtual  = item && item.classList.contains( 'wpfb-virtual' );
			const renameItem = this.contextMenu.querySelector( '[data-action="rename"]' );
			const deleteItem = this.contextMenu.querySelector( '[data-action="delete"]' );
			const subItem    = this.contextMenu.querySelector( '[data-action="new-subfolder"]' );

			if ( renameItem ) renameItem.style.display = isVirtual || ! item ? 'none' : '';
			if ( deleteItem ) deleteItem.style.display = isVirtual || ! item ? 'none' : '';
			if ( subItem ) subItem.style.display = ! item ? 'none' : '';

			this.contextMenu.style.display = '';
			this.contextMenu.style.left    = x + 'px';
			this.contextMenu.style.top     = y + 'px';
		}

		/**
		 * Hide the context menu.
		 */
		_hideContextMenu() {
			if ( this.contextMenu ) {
				this.contextMenu.style.display = 'none';
			}
		}

		/**
		 * Handle a context menu action.
		 *
		 * @param {string} action
		 */
		_handleContextAction( action ) {
			const item   = this.ctxTarget;
			const id     = item ? item.dataset.id : null;

			switch ( action ) {
				case 'new-folder':
					this._promptNewFolder( 0 );
					break;
				case 'new-subfolder':
					if ( id && id !== '-1' ) {
						this._promptNewFolder( parseInt( id, 10 ) );
					}
					break;
				case 'rename':
					if ( item && ! item.classList.contains( 'wpfb-virtual' ) ) {
						this._startRename( item );
					}
					break;
				case 'delete':
					if ( item && ! item.classList.contains( 'wpfb-virtual' ) ) {
						this._deleteFolder( item );
					}
					break;
			}
		}

		/**
		 * Prompt for a new folder name and create it.
		 *
		 * @param {number} parentId
		 */
		_promptNewFolder( parentId ) {
			const name = prompt( wpfbData.i18n.newFolder );
			if ( ! name || ! name.trim() ) return;

			api( 'POST', '/folders', {
				name        : name.trim(),
				context_key : this.context,
				parent      : parentId,
				order       : 0,
			} ).then( ( folder ) => {
				this._addFolderToTree( folder, parentId );
			} ).catch( () => {
				// Silent fail — WP will show any server errors.
			} );
		}

		/**
		 * Insert a new folder <li> into the tree.
		 *
		 * @param {object} folder Folder data from REST API.
		 * @param {number} parentId
		 */
		_addFolderToTree( folder, parentId ) {
			const li = this._buildFolderLI( folder );

			if ( parentId === 0 ) {
				this.tree.appendChild( li );
			} else {
				const parentItem = this.tree.querySelector( `[data-id="${ parentId }"]` );
				if ( ! parentItem ) {
					this.tree.appendChild( li );
					return;
				}

				let children = parentItem.querySelector( '.wpfb-folder-children' );
				if ( ! children ) {
					children = document.createElement( 'ul' );
					children.className = 'wpfb-folder-children';
					children.setAttribute( 'role', 'group' );
					parentItem.appendChild( children );
					parentItem.classList.add( 'wpfb-has-children' );

					// Add toggle button if missing.
					const node = parentItem.querySelector( '.wpfb-folder-node' );
					const placeholder = parentItem.querySelector( '.wpfb-toggle-placeholder' );
					if ( node && placeholder ) {
						const btn = document.createElement( 'button' );
						btn.type = 'button';
						btn.className = 'wpfb-toggle-btn';
						btn.setAttribute( 'aria-label', wpfbData.i18n.newFolder );
						btn.innerHTML = '&#9658;';
						placeholder.replaceWith( btn );
					}
				}

				children.style.display = '';
				parentItem.setAttribute( 'aria-expanded', 'true' );
				children.appendChild( li );
			}
		}

		/**
		 * Build a <li> element for a folder.
		 *
		 * @param {object} folder
		 * @returns {HTMLElement}
		 */
		_buildFolderLI( folder ) {
			const li = document.createElement( 'li' );
			li.className = 'wpfb-folder-item';
			li.dataset.id     = folder.id;
			li.dataset.parent = folder.parent;
			li.dataset.order  = folder.order;
			li.setAttribute( 'role', 'treeitem' );
			li.setAttribute( 'aria-expanded', 'false' );
			li.setAttribute( 'aria-selected', 'false' );
			li.setAttribute( 'draggable', 'true' );

			li.innerHTML = `
				<span class="wpfb-folder-node">
					<span class="wpfb-toggle-placeholder"></span>
					<img src="${ wpfbData.folderIcon }" class="wpfb-folder-icon" alt="" />
					<span class="wpfb-folder-name">${ this._esc( folder.name ) }</span>
					<span class="wpfb-folder-count">${ folder.count || 0 }</span>
				</span>
			`;

			return li;
		}

		/**
		 * HTML-escape a string.
		 *
		 * @param {string} str
		 * @returns {string}
		 */
		_esc( str ) {
			const d = document.createElement( 'div' );
			d.appendChild( document.createTextNode( str ) );
			return d.innerHTML;
		}

		/**
		 * Start inline rename for a folder item.
		 *
		 * @param {HTMLElement} item
		 */
		_startRename( item ) {
			const nameSpan = item.querySelector( '.wpfb-folder-name' );
			if ( ! nameSpan ) return;

			const currentName = nameSpan.textContent;
			const input       = document.createElement( 'input' );
			input.type        = 'text';
			input.className   = 'wpfb-rename-input';
			input.value       = currentName;

			nameSpan.replaceWith( input );
			input.focus();
			input.select();

			const finish = () => {
				const newName = input.value.trim();
				if ( newName && newName !== currentName ) {
					api( 'PUT', `/folders/${ item.dataset.id }`, { name: newName } )
						.then( ( folder ) => {
							const span = document.createElement( 'span' );
							span.className = 'wpfb-folder-name';
							span.textContent = folder.name;
							input.replaceWith( span );
						} )
						.catch( () => {
							const span = document.createElement( 'span' );
							span.className = 'wpfb-folder-name';
							span.textContent = currentName;
							input.replaceWith( span );
						} );
				} else {
					const span = document.createElement( 'span' );
					span.className = 'wpfb-folder-name';
					span.textContent = currentName;
					input.replaceWith( span );
				}
			};

			input.addEventListener( 'blur', finish );
			input.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
					finish();
				} else if ( e.key === 'Escape' ) {
					const span = document.createElement( 'span' );
					span.className = 'wpfb-folder-name';
					span.textContent = currentName;
					input.replaceWith( span );
				}
			} );
		}

		/**
		 * Delete a folder after confirmation.
		 *
		 * @param {HTMLElement} item
		 */
		_deleteFolder( item ) {
			if ( ! confirm( wpfbData.i18n.confirmDelete ) ) return;

			api( 'DELETE', `/folders/${ item.dataset.id }` )
				.then( () => {
					item.remove();
					// If deleted item was selected, reset to "All Items".
					if ( this.activeId === item.dataset.id ) {
						this._selectNode( this.tree.querySelector( '[data-id="-1"]' ) );
					}
				} )
				.catch( () => {} );
		}
	}

	/**
	 * Initialize the folder tree on DOMContentLoaded.
	 */
	function init() {
		const sidebar = document.getElementById( 'wpfb-sidebar' );
		if ( ! sidebar ) return;

		window.wpfbTree = new WPFBTree( sidebar );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
