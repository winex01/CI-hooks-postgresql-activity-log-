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

        $this->createTableIfNotExist();
    }

    public function log_activity()
    {   

        $user = $this->CI->session->userdata('username');

        // do not run record activity log if not authenticated
        if (empty($user) || !isset($user)) {
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

    private function createTableIfNotExist()
    {
        $sql =  "
                CREATE SCHEMA IF NOT EXISTS setup;

                CREATE TABLE IF NOT EXISTS setup.activity_log
                (
                    application character varying NOT NULL,
                    module character varying NOT NULL,
                    tab character varying NOT NULL,
                    action character varying NULL,
                    post_data character varying,
                    username character varying(100) NOT NULL,
                    time_stamp timestamp without time zone NOT NULL DEFAULT now(),
                    user_agent character varying,
                    ip_address character varying
                )
                WITH (
                    OIDS=FALSE
                );
                ALTER TABLE IF EXISTS setup.activity_log
                    OWNER TO postgres;

                CREATE TABLE IF NOT EXISTS setup.log_query
                (
                    username character varying(100) NOT NULL,
                    ip_address character varying,
                    user_agent character varying,
                    time_stamp timestamp without time zone NOT NULL DEFAULT now(),
                    query character varying
                )
                WITH (
                    OIDS=FALSE
                );
                ALTER TABLE IF EXISTS setup.log_query
                    OWNER TO postgres;
                ";

        $this->executeQuery($sql, $this->dbLog->database);

    }//end createTableIfNotExist

    private function executeQuery($sql, $dbName = 'template1')
    {

        $con = pg_connect("
            host=".$this->dbLog->hostname." 
            port=".$this->dbLog->port." 
            dbname=".$dbName." 
            user=".$this->dbLog->username." 
            password=".$this->dbLog->password
        );

        if(pg_connection_status($con) !== PGSQL_CONNECTION_OK) {
            echo "Failed to connect to database.\n";
            exit(1);
        }
            
        pg_query($con, $sql);
    
        pg_close($con);

    }//end executeQuery
}

/* End of file CI_hook_log.php */
/* Location: ./application/hooks/CI_hook_log.php */
