<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class CI_Hook_Log
{
    private $CI;

    private $dbLog;

    public function __construct()
    {
        $this->CI =& get_instance();

        $this->CI->config->load("log");

        $this->dbLog = $this->CI->load->database('log', TRUE);
    }

    public function log_activity()
    {

        $user = $this->CI->session->userdata('username');

        // do not run record activity log if not authenticated
        if (empty($user) || !isset($user)) {
            return;
        }

        $is_logged  = $this -> CI -> config -> item("db_log_activity_activity");

        if (!$is_logged) {
            return;
        }

        $application = $this->CI->config->item('index_page');

        $module      = getURI(0);
        $tab         = getURI(1);
        $action      = getURI(2) . ' '. getURI(3) . ' '. getURI(4) . ' '. getURI(5) . ' '. getURI(6);

        $action      =( empty(getURI(2)) ) ? 'view' : $action;

        $post_data   = $this->CI->input->post();

        $post_data   = (empty($post_data) || !$post_data) ? null : json_encode($post_data);

        $username    = $this->CI->session->userdata("username");

        $user_agent  = $this->CI->session->userdata("user_agent");

        $ip_address  = $this->CI->session->userdata("ip_address");


        $query       = $this->CI->db->query('SELECT module_code FROM module WHERE module_id = ?', [$module])->row();

        $data = [
            'application' => $application,
            'module'      => $query->module_code,
            'tab'         => $tab,
            'action'      => $action,
            'post_data'   => $post_data,
            'username'    => $username,
            'user_agent'  => $user_agent,
            'ip_address'  => $ip_address,
        ];

        if (empty($post_data) ) {
            unset($data['post_data']);
        }

        $this->dbLog->insert('setup.activity_log', $data);

    }//end log_activity

    public function log_query()
    {
        /* Additional step so that guest will also be logged */
        $username   = $this -> CI -> session -> userdata("username") ? $this -> CI -> session -> userdata("username") : "guest";
        $user_agent = $this -> CI -> session -> userdata("user_agent");
        $ip_address = $this -> CI -> session -> userdata("ip_address");

        $is_logged  = $this -> CI -> config -> item("db_log_activity_query");
        $queries    = $this -> CI -> db -> queries;

        if ($is_logged && count($queries) > 0 && $username) {
            $data = array();

            foreach ($queries as $query) {
                $data[] = array(
                    "username"      => $username,
                    "user_agent"    => $user_agent,
                    "ip_address"    => $ip_address,
                    "query"         => $query
                );
            }

            $dbLog = $this -> CI -> load -> database("log", TRUE);

            $table = $this -> CI -> config -> item("sess_log_schema_query");

            $dbLog -> insert_batch($table, $data);

            $dbLog -> close();
        }
    }
}

/* End of file CI_hook_log.php */
/* Location: ./application/hooks/CI_hook_log.php */
