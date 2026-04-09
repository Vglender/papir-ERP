<?php
/**
 * Papir ERP Agent — Admin Controller (OpenCart 2.x)
 *
 * Handles install/uninstall, token generation, and settings page.
 */
class ControllerExtensionModulePapirAgent extends Controller {

    private $error = array();

    // OC2 uses 'token', OC3 uses 'user_token'
    private function getAdminToken() {
        if (isset($this->session->data['user_token'])) {
            return 'user_token=' . $this->session->data['user_token'];
        }
        if (isset($this->session->data['token'])) {
            return 'token=' . $this->session->data['token'];
        }
        return '';
    }

    public function index() {
        $this->load->language('extension/module/papir_agent');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $adminToken = $this->getAdminToken();

        // Save settings
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('papir_agent', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/papir_agent', $adminToken, true));
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $adminToken, true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', $adminToken . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/papir_agent', $adminToken, true)
        );

        // Token & status
        $data['papir_agent_status'] = isset($this->request->post['papir_agent_status'])
            ? $this->request->post['papir_agent_status']
            : $this->config->get('papir_agent_status');

        $data['papir_agent_token'] = $this->config->get('papir_agent_token');
        if (empty($data['papir_agent_token'])) {
            $data['papir_agent_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }

        // API URL
        $storeUrl = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_CATALOG;
        $data['api_url'] = $storeUrl . 'index.php?route=extension/module/papir_agent';

        // Messages
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        // Actions
        $data['action'] = $this->url->link('extension/module/papir_agent', $adminToken, true);
        $data['cancel'] = $this->url->link('extension/extension', $adminToken . '&type=module', true);
        $data['regenerate'] = $this->url->link('extension/module/papir_agent/regenerate', $adminToken, true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/papir_agent', $data));
    }

    /**
     * Regenerate API token
     */
    public function regenerate() {
        $this->load->model('setting/setting');

        $settings = array(
            'papir_agent_status' => $this->config->get('papir_agent_status'),
            'papir_agent_token'  => bin2hex(openssl_random_pseudo_bytes(32)),
        );
        $this->model_setting_setting->editSetting('papir_agent', $settings);

        $this->load->language('extension/module/papir_agent');
        $this->session->data['success'] = $this->language->get('text_token_regenerated');
        $this->response->redirect($this->url->link('extension/module/papir_agent', $this->getAdminToken(), true));
    }

    /**
     * Install: generate token, set active
     */
    public function install() {
        $this->load->model('setting/setting');

        $settings = array(
            'papir_agent_status' => 1,
            'papir_agent_token'  => bin2hex(openssl_random_pseudo_bytes(32)),
        );
        $this->model_setting_setting->editSetting('papir_agent', $settings);
    }

    /**
     * Uninstall: remove settings
     */
    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('papir_agent');
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/papir_agent')) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }
        return true;
    }
}