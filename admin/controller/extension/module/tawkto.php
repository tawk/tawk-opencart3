<?php
/**
 * @package tawk.to Integration
 * @author tawk.to
 * @copyright (C) 2021 tawk.to
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

class ControllerExtensionModuleTawkto extends Controller {
    private $error = array();

    private function setup() {
        $this->load->language('extension/module/tawkto');

        $this->load->model('setting/setting');
        $this->load->model('design/layout');
        $this->load->model('setting/store');
        $this->load->model('localisation/language');

        //calling layout enable again ensures, that even if new
        //layouts are added widget will show up on those
        //new layouts
        $this->enableAllLayouts();
    }

    public function index() {

        $this->setup();
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/module');

        $data = $this->setupIndexTexts();
        $data['module_tawkto_status'] = $this->config->get('module_tawkto_status');

        // get current store and load tawk.to options
        $store_id = 0;
        $stores = $this->model_setting_store->getStores();
        if (!empty($stores)) {
            foreach ($stores as $store) {
                if ($this->config->get('config_url') == $store['url']) {
                    $store_id = intval($store['store_id']);
                }
            }
        }

        $data['base_url']   = $this->getBaseUrl();
        $data['iframe_url'] = $this->getIframeUrl();
        $data['hierarchy']  = $this->getStoreHierarchy();
        $data['url'] = array(
                'set_widget_url' => $this->url->link('extension/module/tawkto/setwidget', '', 'SSL') . '&user_token=' . $this->session->data['user_token'],
                'remove_widget_url' => $this->url->link('extension/module/tawkto/removewidget', '', 'SSL') . '&user_token=' . $this->session->data['user_token'],
                'set_options_url' => $this->url->link('extension/module/tawkto/setoptions', '', 'SSL') . '&user_token=' . $this->session->data['user_token']
            );

        $data['widget_config']  = $this->getConfig($store_id);
        $data['same_user'] = true;
        if (isset($data['widget_config']['user_id'])) {
            $data['same_user']  = ($data['widget_config']['user_id']==$this->session->data['user_id']);
        }

        $data['display_opts']  = $this->getDisplayOpts($store_id);
        $data['show_whitelist']  = (!empty($data['display_opts']['show_oncustom']))?json_decode($data['display_opts']['show_oncustom']):array();
        $data['hide_whitelist']  = (!empty($data['display_opts']['hide_oncustom']))?json_decode($data['display_opts']['hide_oncustom']):array();

        $data['store_id']  = $store_id;
        $data['store_layout_id']  = $store_id; // set default to 0

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['user_token'] = $this->session->data['user_token'];

        $this->response->setOutput($this->load->view('extension/module/tawkto', $data));
    }

    public function getConfig($store_id = 0)
    {
        $config = array(
                'page_id' => null,
                'widget_id' => null
            );

        $current_settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);
        if (isset($current_settings['module_tawkto_widget']['widget_config_'.$store_id])) {
            $config = $current_settings['module_tawkto_widget']['widget_config_'.$store_id];
        }

        return $config;
    }

    public function getDisplayOpts($store_id = 0)
    {
        $current_settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);

        $options = array(
                'always_display' => true,
                'hide_oncustom' => array(),
                'show_onfrontpage' => false,
                'show_oncategory' => false,
                'show_oncustom' => array(),
                'monitor_customer_cart' => false,
                'enable_visitor_recognition' => true
            );
        if (isset($current_settings['module_tawkto_visibility'])) {
            $options = $current_settings['module_tawkto_visibility'];
            $options = json_decode($options,true);
        }

        return $options;
    }

    /**
     * Page id is mongodb object id and widget id is alpanumeric
     * string
     *
     * @return Boolean
     */
    private function validatePost() {
        if (!isset($_POST['pageId']) || !isset($_POST['widgetId']) || !isset($_POST['store'])) {
            return false;
        }

        $page_id = $this->db->escape($_POST['pageId']);
        $widget_id = $this->db->escape($_POST['widgetId']);
        $store = isset($_POST['store'])?intval($_POST['store']):null;

        return (!empty($page_id) && !empty($widget_id) && !is_null($store))
            && preg_match('/^[0-9A-Fa-f]{24}$/', $page_id) === 1
            && preg_match('/^[a-z0-9]{1,50}$/i', $widget_id) === 1;
    }

    public function setoptions() {
        header('Content-Type: application/json');

        $jsonOpts = array(
                'always_display' => false,
                'hide_oncustom' => array(),
                'show_onfrontpage' => false,
                'show_oncategory' => false,
                'show_onproduct' => false,
                'show_oncustom' => array(),
                'monitor_customer_cart' => false,
                'enable_visitor_recognition' => false
            );

        if (isset($_REQUEST['options']) && !empty($_REQUEST['options'])) {

            $options = explode('&', $_REQUEST['options']);

            foreach ($options as $post) {
                list($column, $value) = explode('=', $post);
                $column = str_ireplace('amp;', '', $column);
                switch ($column) {
                    case 'hide_oncustom':
                    case 'show_oncustom':
                        // replace newlines and returns with comma, and convert to array for saving
                        $value = urldecode($value);
                        $value = str_ireplace(array("\r\n", "\r", "\n"), ',', $value);
                        $value = explode(",", $value);
                        $value = (empty($value)||!$value)?array():$value;
                        $jsonOpts[$column] = json_encode($value);
                        break;

                    case 'show_onfrontpage':
                    case 'show_oncategory':
                    case 'show_onproduct':
                    case 'always_display':
                    case 'monitor_customer_cart':
                    case 'enable_visitor_recognition':
                        $jsonOpts[$column] = $value == 1;
                        break;
                }
            }
        }

        $this->setup();

        $store_id = isset($_POST['store'])?intval($_POST['store']):null;
        if (is_null($store_id)) {
            echo json_encode(array('success' => false));
            die();
        }

        $current_settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);
        $current_settings['module_tawkto_visibility'] = json_encode($jsonOpts);
        $this->model_setting_setting->editSetting('module_tawkto', $current_settings, $store_id);

        echo json_encode(array('success' => true));
        die();
    }

    public function setwidget() {
        header('Content-Type: application/json');

        if (!$this->validatePost() || !$this->checkPermission()) {
            echo json_encode(array('success' => false));
            die();
        }

        $this->setup();

        $fail = false;

        $id = isset($_POST['id'])?intval($_POST['id']):null;
        if (is_null($id)) {
            $fail = true;
        }

        if (!isset($_POST['pageId']) || !isset($_POST['widgetId'])) {
            $fail = true;
        }
        $page_id = $this->db->escape($_POST['pageId']);
        $widget_id = $this->db->escape($_POST['widgetId']);

        if ($fail) {
            echo json_encode(array('success' => false));
            die();
        }

        $currentSettings = $this->model_setting_setting->getSetting('module_tawkto', $_POST['store']);
        $currentSettings['module_tawkto_widget'] = isset($currentSettings['module_tawkto_widget']) ? $currentSettings['module_tawkto_widget'] : array();
        $currentSettings['module_tawkto_widget']['widget_config_'.$id] = array(
                'page_id' => $page_id,
                'widget_id' => $widget_id,
                'user_id' => $this->session->data['user_id']
            );

        $this->model_setting_setting->editSetting('module_tawkto', $currentSettings, $_POST['store']);

        echo json_encode(array('success' => true));
        die();
    }

    public function removewidget() {
        header('Content-Type: application/json');

        $id = isset($_POST['id'])?intval($_POST['id']):null;
        if (is_null($id) || !$this->checkPermission()) {
            echo json_encode(array('success' => false));
            die();
        }

        $this->setup();

        $currentSettings = $this->model_setting_setting->getSetting('module_tawkto');
        unset($currentSettings['module_tawkto_widget']['widget_config_'.$id]);

        $this->model_setting_setting->editSetting('module_tawkto', $currentSettings, $_POST['id']);

        echo json_encode(array('success' => true));
        die();
    }

    private function setupIndexTexts() {
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', 'token=' . $this->session->data['user_token'], 'SSL'),
                'separator' => false
            );

        $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'token=' . $this->session->data['user_token'], 'SSL'),
                'separator' => ' :: '
            );

        $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/tawkto', 'token=' . $this->session->data['user_token'], 'SSL'),
                'separator' => ' :: '
            );

        $data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['user_token'], 'SSL');

        $data['heading_title'] = $this->language->get('heading_title');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['text_installed'] = $this->language->get('text_installed');
        return $data;
    }

    /**
     * Module supports multistore structure, each store and
     * its languages, layouts can have different widgets
     *
     * @return Array
     */
    private function getStoreHierarchy() {
        $stores = $this->model_setting_store->getStores();
        $this->layouts = $this->model_design_layout->getLayouts();
        $this->languages = $this->model_localisation_language->getLanguages();

        $hierarchy = array();

        // we need to empty childs as these prevent us from monitoring user
        // and user's custom attributes as they navigate the store
        // (incoming feature, e.g. setting diff. widget per template)
        $hierarchy[] = array(
                'id'      => '0',
                'name'    => 'Default store',
                'current' => $this->getCurrentSettingsFor('0'),
                'childs'  => array()
            );

        foreach($stores as $store) {
            $hierarchy[] = array(
                    'id'      => $store['store_id'],
                    'name'    => $store['name'],
                    'current' => $this->getCurrentSettingsFor($store['store_id']),
                    'childs'  => array()
                );
        }

        return $hierarchy;
    }

    /**
     * Each store can have more than one language
     * and this module allows to change widget for
     * each language separately
     *
     * @param  String $parent
     * @return Array
     */
    private function getLanguageHierarchy($parent) {
        $return = array();

        foreach($this->languages as $code => $details) {
            $return[] = array(
                    'id' => $parent . '_' . $details['language_id'],
                    'name' => $details['name'],
                    'current' => $this->getCurrentSettingsFor($parent.'_'.$details['language_id']),
                    'childs' => $this->getLayoutHierarchy($parent.'_'.$details['language_id'])
                );
        }

        return $return;
    }

    /**
     * Builds layout list with current populating that with correct
     * value based on store and language, this is the last level
     *
     * @param  String $parent
     * @return Array
     */
    private function getLayoutHierarchy($parent) {
        $return = array();

        foreach ($this->layouts as $layout) {
            $return[] = array(
                'id' => $parent . '_' . $layout['layout_id'],
                'name' => $layout['name'],
                'childs' => array(),
                'current' => $this->getCurrentSettingsFor($parent.'_'.$layout['layout_id'])
            );
        }

        return $return;
    }

    /**
     * Will retrieve widget settings for supplied item in hierarchy
     * It can be store, store + language or store+language+layout
     *
     * @param  Int $id
     * @return Array
     */
    private function getCurrentSettingsFor($id) {

        $this->currentSettings = $this->model_setting_setting->getSetting('module_tawkto', $id);

        if(isset($this->currentSettings['module_tawkto_widget']['widget_config_'.$id])) {
            $settings = $this->currentSettings['module_tawkto_widget']['widget_config_'.$id];

            return array(
                'pageId'   => $settings['page_id'],
                'widgetId' => $settings['widget_id']
            );
        } else {
            return array();
        }
    }

    private function getIframeUrl() {
        $settings = $this->model_setting_setting->getSetting('module_tawkto');

        return $this->getBaseUrl()
            .'/generic/widgets/'
            .'?selectType=singleIdSelect'
            .'&selectText=Store';
    }

    private function getBaseUrl() {
        return 'https://plugins.tawk.to';
    }

    public function install() {
        $this->setup();
        $this->model_setting_setting->editSetting('module_tawkto', array("module_tawkto_status" => 1));
        $this->enableAllLayouts();
    }

    public function uninstall() {
        $this->setup();
        $this->model_setting_setting->deleteSetting('module_tawkto');
    }

    private function enableAllLayouts() {
        $layouts = $this->model_design_layout->getLayouts();

        //will enable tawk.to module in every page/layout there is
        foreach ($layouts as $layout) {
            $layout_id = $this->db->escape($layout['layout_id']);

            $this->db->query("INSERT INTO " . DB_PREFIX . "layout_module (layout_id, code, position, sort_order)
                SELECT '" . $layout_id . "', 'tawkto', 'content_bottom', '999' FROM dual
                WHERE NOT EXISTS (
                    SELECT layout_module_id
                    FROM " . DB_PREFIX . "layout_module
                    WHERE layout_id = '" . $layout_id . "' AND code = 'tawkto'
                )
                LIMIT 1
            ");
        }
    }

    protected function checkPermission() {
        if (!$this->user->hasPermission('modify', 'extension/module/tawkto')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
