<?php
/**
 * Plugin Name: CE WP-Menu per Page
 * Plugin URI: http://ppfeufer.de/wordpress-plugin/ce-wp-menu-per-page/
 * Description: This plugin allows you to select a menu on a per page basis.
 * Version: 1.2
 * Author: Codeenterprise (H.-Peter Pfeufer)
 * Author URI: http://codeenterprise.de
 * Text Domain: ce-wp-menu-per-page
 * Domain Path: /l10n
 */
if(!class_exists('Ce_Wp_Menu_Per_Page')) {
	class Ce_Wp_Menu_Per_Page {
		private $textdomain = 'ce-wp-menu-per-page';								// Textdomain für die Übersetzung
		private $posttype = 'page';													// Posttype (hier page für Seiten)
		private $metaname_menu = 'ce-wp-menu-per-page';								// Name des Custom Fields
		private $metaname_menu_position = 'ce-wp-menu-per-page-position';			// Name des Custom Fields
		private $metaboxID = 'ce-wp-menu-per-page';									// ID der Metabox
		private $noncename = 'ce-wp-menu-per-page';									// Name des Nonce (etwas für die Sicherheit
		private $defaultmenu = 'wp-default';										// "Slug" des Standardmenüs
		private $defaultmenu_position = 'primary';									// "Slug" des Standardmenüs
		private $userright = 'edit_page';											// Nutzerrechte die benötigt werden
		private $menu_selectname = 'ce-wp-menu-per-page-page-menu';					// Name des Selectfeldes des Menüs
		private $position_selectname = 'ce-wp-menu-per-page-page-menu_position';	// Name des Selectfeldes der Menüposition

		/**
		 * Constructor (old style)
		 *
		 * @uses __construct
		 */
		function Ce_Wp_Menu_Per_Page() {
			self::__construct();
		} // END function Ce_Wp_Menu_Per_Page()

		/**
		 * Constructor
		 */
		function __construct() {
			// Backend
			if(is_admin()) {
				add_action('admin_init', array(
					$this,
					'_plugin_init'
				));

				add_action('add_meta_boxes', array(
					$this,
					'_add_meta_box'
				));

				add_action('save_post', array(
					$this,
					'_save_page_menu'
				));
			} // END if(is_admin())

			// Frontend
			if(!is_admin()) {
				add_filter('wp_nav_menu_args', array(
					$this,
					'_menu_per_page'
				));
			} // END if(!is_admin())
		} // END function __construct()

		function _plugin_init() {
			/**
			 * Sprachdatei wählen
			 */
			if(function_exists('load_plugin_textdomain')) {
				load_plugin_textdomain($this->textdomain, false, dirname(plugin_basename( __FILE__ )) . '/l10n/');
			} // END if(function_exists('load_plugin_textdomain'))
		} // END function _plugin_init()

		/**
		 * Metabox am System anmelden
		 */
		function _add_meta_box() {
			add_meta_box($this->metaboxID, __('Select the menu for this page', $this->textdomain), array(
				$this,
				'the_meta_box'
			), $this->posttype, 'normal', 'high');
		} // END function _add_meta_box()

		/**
		 * Metabox erstellen
		 */
		function the_meta_box() {
			global $post;

			// Menüs abholen
			$menues = wp_get_nav_menus();
			$menu_locations = get_registered_nav_menus();

			if(!empty($menues) && count($menues) != 0) {
				// Use nonce for verification
				wp_nonce_field(plugin_basename( __FILE__ ), $this->noncename);

				// Ist bereits ein Menü gewählt?
				$menuslug = get_post_meta($post->ID, $this->metaname_menu, true);
				$menuposition = get_post_meta($post->ID, $this->metaname_menu_position, true);

				// Setting the defaultmenu
				if(empty($menuslug)) {
					$menuslug = $this->defaultmenu;
				} // END if(empty($menu_name))

				$array_DefaultMenu['wp-default'] = new stdClass();
				$array_DefaultMenu['wp-default']->name = __('WordPress Default', $this->textdomain);
				$array_DefaultMenu['wp-default']->slug = 'wp-default';

				$menues = array_merge($array_DefaultMenu, (array) $menues);

				echo sprintf('<p>%1$s</p>', __('Please select the menu which should be displayed on this page.', $this->textdomain));
				echo '<select name="' . $this->menu_selectname . '">';

				foreach($menues as $menu) {
					if(!empty($menuslug)) {
						$selected = '';

						if($menuslug == $menu->slug) {
							$selected = ' selected="selected"';
						} // END if($menu_name == $menu->slug)
					} // END if(!empty($menu_name))

					echo '<option value="' . $menu->slug . '"' . $selected . '>' . $menu->name . '</option>';
				} // END foreach($menues as $menu)

				echo '</select>';

				if(is_array($menu_locations) && count($menu_locations) != 0) {
					echo sprintf('<p>%1$s</p>', __('Please select the menu location, where your menu should appear', $this->textdomain));
					echo '<select name="' . $this->position_selectname . '">';

					foreach($menu_locations as $slug => $position) {
						if(!empty($menuposition)) {
							$selected = '';

							if($menuposition == $slug) {
								$selected = ' selected="selected"';
							} // END if($menu_name == $menu->slug)
						} // END if(!empty($menu_name))

						echo '<option value="' . $slug . '"' . $selected . '>' . $position . '</option>';
					} // END foreach($menues as $menu)

					echo '</select>';
				} // END if(is_array($menu_locations) && count($menu_locations) != 0)
			} // END if(!empty($menues) && count($menues) != 0)
		} // END function the_meta_box()

		/**
		 * Daten speichern
		 *
		 * @param int $post_id
		 */
		function _save_page_menu($post_id) {
			// Erst mal schauen wir, ob der Nutzer das überhaupt darf
			if(!current_user_can($this->userright, $post_id)) {
				return;
			}

			// Dann prüfen wir die Nonces
			if(!isset($_REQUEST[$this->noncename]) || !wp_verify_nonce($_REQUEST[$this->noncename], plugin_basename(__FILE__))) {
				return;
			} // END if(!isset($_REQUEST['vokabel_page_menu']) || !wp_verify_nonce($_REQUEST['vokabel_page_menu'], plugin_basename(__FILE__)))

			// und nun wird der ganze Hokuspokus gespeichert
			$post_id = $_REQUEST['post_ID'];

			/**
			 * Saving the Settings
			 */
			if($_REQUEST[$this->menu_selectname] == $this->defaultmenu) {
				// Get the old slug
				$menuslug = get_post_meta($post->ID, $this->metaname_menu, true);

				if(empty($menuslug)) {
					delete_post_meta($post_id, $this->metaname_menu);
					delete_post_meta($post_id, $this->metaname_menu_position);
				} // END if(empty($menu_name))
			} else {
				// Metainformationen hinzufügen oder aktualisieren
				add_post_meta($post_id, $this->metaname_menu, $_REQUEST[$this->menu_selectname], true) or update_post_meta($post_id, $this->metaname_menu, $_REQUEST[$this->menu_selectname]);
				add_post_meta($post_id, $this->metaname_menu_position, $_REQUEST[$this->position_selectname], true) or update_post_meta($post_id, $this->metaname_menu_position, $_REQUEST[$this->position_selectname]);
			} // END // END if($_REQUEST[$this->menu_selectname] == $this->defaultmenu)
		} // END function _save_page_menu($post_id)

		/**
		 * Menü im Frontend anzeigen
		 *
		 * @param array $args
		 * @return Ambigous <mixed, string, multitype:, boolean, array, string>
		 */
		function _menu_per_page($args = '') {
			global $post;

			if(!empty($post)) {
				$menuslug = get_post_meta($post->ID, $this->metaname_menu, true);
				$menuposition = get_post_meta($post->ID, $this->metaname_menu_position, true);

				if(empty($menuposition)) {
					$menuposition = $this->defaultmenu_position;
				} // END if(empty($menuposition))

				if(is_page() && $args['theme_location'] == $menuposition) {
					if(!empty($menuslug) && is_nav_menu($menuslug)) {
						$args['menu'] = $menuslug;
					} // END if(!empty($menu_name) && is_nav_menu($menu_name))
				} // END if(is_page())
			} // END if(!empty($post))

			return $args;
		} // END function _menu_per_page($args = '')
	} // END class Ce_Wp_Menu_Per_Page

	// Klasse starten
	new Ce_Wp_Menu_Per_Page();
} // END if(!class_exists('Ce_Wp_Menu_Per_Page'))