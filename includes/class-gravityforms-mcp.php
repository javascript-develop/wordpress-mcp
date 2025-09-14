<?php
/**
 * GravityForms MCP integration for WordPress MCP plugin
 */

class GravityForms_MCP_Integration {
    
    public function __construct() {
        add_filter('wordpress_mcp_tools', array($this, 'register_gravityforms_tools'));
        add_filter('wordpress_mcp_handle_tool', array($this, 'handle_gravityforms_tool'), 10, 3);
    }
    
    public function register_gravityforms_tools($tools) {
        // Only add tools if GravityForms is active
        if (!class_exists('GFForms')) {
            return $tools;
        }
        
        $gravityforms_tools = array(
            array(
                'name' => 'gravityforms_list_forms',
                'description' => 'List all GravityForms forms',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'limit' => array(
                            'type' => 'number',
                            'description' => 'Number of forms to return (default: 20)',
                        ),
                        'offset' => array(
                            'type' => 'number',
                            'description' => 'Offset for pagination',
                        ),
                    ),
                ),
            ),
            array(
                'name' => 'gravityforms_get_form',
                'description' => 'Get details of a specific form',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'form_id' => array(
                            'type' => 'number',
                            'description' => 'ID of the form to retrieve',
                            'required' => true,
                        ),
                    ),
                ),
            ),
            array(
                'name' => 'gravityforms_create_form',
                'description' => 'Create a new form',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'title' => array(
                            'type' => 'string',
                            'description' => 'Form title',
                            'required' => true,
                        ),
                        'description' => array(
                            'type' => 'string',
                            'description' => 'Form description',
                        ),
                    ),
                ),
            ),
            array(
                'name' => 'gravityforms_get_entries',
                'description' => 'Get form entries',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'form_id' => array(
                            'type' => 'number',
                            'description' => 'ID of the form',
                            'required' => true,
                        ),
                        'limit' => array(
                            'type' => 'number',
                            'description' => 'Number of entries to return',
                        ),
                    ),
                ),
            ),
            array(
                'name' => 'gravityforms_create_entry',
                'description' => 'Create a new form entry',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'form_id' => array(
                            'type' => 'number',
                            'description' => 'ID of the form',
                            'required' => true,
                        ),
                        'data' => array(
                            'type' => 'object',
                            'description' => 'Entry data with field values',
                            'required' => true,
                        ),
                    ),
                ),
            ),
        );
        
        return array_merge($tools, $gravityforms_tools);
    }
    
    public function handle_gravityforms_tool($result, $tool_name, $args) {
        if (strpos($tool_name, 'gravityforms_') === 0) {
            $actual_tool_name = str_replace('gravityforms_', '', $tool_name);
            
            switch ($actual_tool_name) {
                case 'list_forms':
                    return $this->list_forms($args);
                case 'get_form':
                    return $this->get_form($args);
                case 'create_form':
                    return $this->create_form($args);
                case 'get_entries':
                    return $this->get_entries($args);
                case 'create_entry':
                    return $this->create_entry($args);
            }
        }
        
        return $result;
    }
    
    private function make_gf_request($endpoint, $method = 'GET', $data = array()) {
        $url = rest_url('gf/v2/' . $endpoint);
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );
        
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    private function list_forms($args) {
        $limit = isset($args['limit']) ? intval($args['limit']) : 20;
        $offset = isset($args['offset']) ? intval($args['offset']) : 0;
        
        return $this->make_gf_request("forms?limit=$limit&offset=$offset");
    }
    
    private function get_form($args) {
        if (!isset($args['form_id'])) {
            return array('error' => 'form_id is required');
        }
        
        $form_id = intval($args['form_id']);
        return $this->make_gf_request("forms/$form_id");
    }
    
    private function create_form($args) {
        if (!isset($args['title'])) {
            return array('error' => 'title is required');
        }
        
        $form_data = array(
            'title' => sanitize_text_field($args['title']),
            'description' => isset($args['description']) ? sanitize_text_field($args['description']) : '',
        );
        
        return $this->make_gf_request('forms', 'POST', $form_data);
    }
    
    private function get_entries($args) {
        if (!isset($args['form_id'])) {
            return array('error' => 'form_id is required');
        }
        
        $form_id = intval($args['form_id']);
        $limit = isset($args['limit']) ? intval($args['limit']) : 20;
        
        return $this->make_gf_request("entries?form_ids[]=$form_id&limit=$limit");
    }
    
    private function create_entry($args) {
        if (!isset($args['form_id']) || !isset($args['data'])) {
            return array('error' => 'form_id and data are required');
        }
        
        $form_id = intval($args['form_id']);
        $entry_data = array(
            'form_id' => $form_id,
        );
        
        // Merge the data with the entry
        if (is_array($args['data'])) {
            $entry_data = array_merge($entry_data, $args['data']);
        }
        
        return $this->make_gf_request('entries', 'POST', $entry_data);
    }
}

// Initialize the integration
add_action('plugins_loaded', function() {
    new GravityForms_MCP_Integration();
});