<?php
/**
 * @package tawk.to Integration
 * @author tawk.to
 * @copyright (C) 2021 tawk.to
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

class ControllerExtensionModuleTawkto extends Controller {

    //we include embed script only once even if more than one layout is displayed
    private static $displayed = false;

    public function index() {

        $this->load->language('extension/module/tawkto');

        if(self::$displayed) {
            return;
        }
        self::$displayed = true;

        $widget = $this->getWidget();
        $settings = json_decode($this->getVisibilitySettings());

        if($widget === null) {
            echo '';
            return;
        }

        $data = array();
        $data['page_id'] = $widget['page_id'];
        $data['widget_id'] = $widget['widget_id'];
        $data['current_page'] = htmlspecialchars_decode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
        $data['logged_in'] = $this->customer->isLogged();
        $data['visitor'] = $this->getVisitor();

        // custom cart attributes
        $data['cart_data'] = array();
        $data['customer'] = array();
        $data['orders'] = array();
        $data['can_monitor_customer_cart'] = false;
        $data['enable_visitor_recognition'] = true; // default

        if (!is_null($this->customer->getId())) {
            $data['customer'] = $this->customer;
        }

        if (!is_null($settings)) {
            if (!is_null($settings->monitor_customer_cart)) {
                $data['can_monitor_customer_cart'] = $settings->monitor_customer_cart;
            }

            if (!is_null($settings->enable_visitor_recognition)) {
                $data['enable_visitor_recognition'] = $settings->enable_visitor_recognition;
            }
        }

        return $this->load->view('extension/module/tawkto', $data);
    }

    public function getVisitor()
    {
        $logged_in = $this->customer->isLogged();
        if ($logged_in) {
            $data = array(
                    'name' => $logged_in?$this->customer->getFirstName().' '.$this->customer->getLastName():null,
                    'email' => $logged_in?$this->customer->getEmail():null,
                );
            return json_encode($data);
        }

        return null;
    }

    private function getWidget() {

        $this->load->model('setting/setting');
        $store_id = $this->config->get('config_store_id');
        $settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);
        $language_id = $this->config->get('config_language_id');
        $layout_id = $this->getLayoutId();

        $visibility = false;
        if (isset($settings['module_tawkto_visibility'])) {
            $visibility = $settings['module_tawkto_visibility'];
        }

        $widget = null;
        if(!isset($settings['module_tawkto_widget'])) {
            return null;
        }
        $settings = $settings['module_tawkto_widget'];

        if(isset($settings['widget_config_'.$store_id])) {
            $widget = $settings['widget_config_'.$store_id];
        }

        if(isset($settings['widget_config_'.$store_id.'_'.$language_id])) {
            $widget = $settings['widget_config_'.$store_id.'_'.$language_id];
        }

        if(isset($settings['widget_config_'.$store_id.'_'.$language_id.'_'.$layout_id])) {
            $widget = $settings['widget_config_'.$store_id.'_'.$language_id.'_'.$layout_id];
        }

        // get visibility options
        if ($visibility) {
            $visibility = json_decode($visibility);

            // prepare visibility
            $request_uri = trim($_SERVER["REQUEST_URI"]);
            if (stripos($request_uri, '/')===0) {
                $request_uri = substr($request_uri, 1);
            }
            $current_page = $this->config->get('config_url').$request_uri;

            if (false==$visibility->always_display) {

                // custom pages
                $show_pages = json_decode($visibility->show_oncustom);
                $show = false;

                $current_page = (string) trim($current_page);
                foreach ($show_pages as $slug) {
                    $slug = trim($slug);
                    if (empty($slug)) {
                        continue;
                    }

                    /*use this when testing on a Linux/Win*/
                    // we need to add htmlspecialchars due to slashes added when saving to database
                    // $slug = (string) htmlspecialchars($slug);
                    $slug = (string) urldecode($slug);
                    $slug = str_ireplace($this->config->get('config_url'), '', $slug);

                    /*use this when testing on a Mac*/
                    // we need to add htmlspecialchars due to slashes added when saving to database
                    // $slug = (string) urldecode($slug);

                    $slug = addslashes($slug);
                    if (stripos($current_page, $slug)!==false || $slug == $current_page) {
                        $show = true;
                        break;
                    }
                }

                // category page
                if (isset($this->request->get['route']) && stripos($this->request->get['route'], 'category')!==false) {
                    if (false!=$visibility->show_oncategory) {
                        $show = true;
                    }
                }

                // home
                $is_home = false;
                if (!isset($this->request->get['route'])
                    || (isset($this->request->get['route']) && $this->request->get['route'] == 'common/home')) {
                    $is_home = true;
                }

                if ($is_home) {
                    if (false!=$visibility->show_onfrontpage) {
                        $show = true;
                    }
                }


                if (!$show) {
                    return;
                }

            } else {
                $show = true;
                $hide_pages = json_decode($visibility->hide_oncustom);

                $current_page = (string) trim($current_page);
                foreach ($hide_pages as $slug) {
                    $slug = trim($slug);
                    if (empty($slug)) {
                        continue;
                    }

                    /*use this when testing on a Linux/Win*/
                    // we need to add htmlspecialchars due to slashes added when saving to database
                    // $slug = (string) htmlspecialchars($slug);
                    $slug = (string) urldecode($slug);
                    $slug = str_ireplace($this->config->get('config_url'), '', $slug);

                    /*use this when testing on a Mac*/
                    // we need to add htmlspecialchars due to slashes added when saving to database
                    // $slug = (string) urldecode($slug);

                    $slug = addslashes($slug);
                    if (stripos($current_page, $slug)!==false || $slug == $current_page) {
                        $show = false;
                        break;
                    }
                }

                if (!$show) {
                    return;
                }
            }
        }

        return $widget;
    }

    private function getVisibilitySettings() {
        $this->load->model('setting/setting');
        $store_id = $this->config->get('config_store_id');
        $settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);

        if (!isset($settings['module_tawkto_visibility'])) {
            return null;
        }

        return $settings['module_tawkto_visibility'];
    }

    private function getLayoutId() {
        if (isset($this->request->get['route'])) {
            $route = $this->request->get['route'];
        } else {
            $route = 'common/home';
        }

        $this->load->model('design/layout');

        return $this->model_design_layout->getLayout($route);
    }
}
