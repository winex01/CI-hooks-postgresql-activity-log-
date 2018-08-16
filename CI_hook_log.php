<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class CI_Hook_Log
{
    private $CI;

    private $db_log;

    public function __construct()
    {
        $this->CI =& get_instance();

        $this->CI->config->load("log");

        $this->db_log = $this->CI->load->database('log', TRUE);

        $this->createDatabaseIfNotExist();

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

        $this->db_log->insert('setup.activity_log', $data);

    }//end log_activity

    public function log_query()
    {
        $session_id = $this->CI->session->userdata("session_id");
        $is_logged  = $this->CI->config->item("db_log_activity_query");
        $queries    = $this->CI->db->queries;

        if ($is_logged && count($queries) > 0 && $session_id) {

            $username   = $this->CI->session->userdata("username");
            $user_agent = $this->CI->session->userdata("user_agent");
            $ip_address = $this->CI->session->userdata("ip_address");        
            $time_stamp = date("c");
            
            foreach ($queries as $query) {
                $query = str_replace('"', '', $query);
                $query = str_replace("'", "\'", $query);
                
                $sql = "INSERT INTO setup.log_query( 
                                             username, 
                                             ip_address, 
                                             user_agent, 
                                             time_stamp, 
                                             query) 
                                      VALUES(
                                            '".$username."',
                                            '".$ip_address."',
                                            '".$user_agent."',
                                            '".$time_stamp."',                                            
                                            E'".$query."')";

                $this -> db_log -> query($sql);
            }
        }
    }//end log_query

    private function createDatabaseIfNotExist()
    {
        // Load database utility
        $this -> CI -> load -> dbutil();

        $dbname = $this->db_log->database;
        $dbuser = $this->db_log->username;

        // Check if log database exists
        if (! $this->CI->dbutil->database_exists($this->db_log->database)) {
            $this->CI->load->helper("log");

            $sql = "CREATE DATABASE $dbname 
                    WITH ENCODING='UTF8'
                         OWNER $dbuser;";

            try {
                // Create log database using the current database settings of the loaded log database
                $con = pg_connect("dbname=template1 user=".$dbuser." password=password");
                
                pg_query($con, $sql);
            
            } catch (Exception $e) {
                
                var_dump($e->getMessage()); die;
            } finally {

                pg_close($con);
            }
        }

        // create table
        $this->createTableIfNotExist($dbname, $dbuser);
        
    }// end createDatabaseIfNotExist

    private function createTableIfNotExist($dbname, $dbuser = 'root')
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
                    OWNER TO postgres;";

        try {
            $con = pg_connect("dbname=".$dbname." user=".$dbuser." password=password");

            pg_query($con, $sql);

        } catch (Exception $e) {

            var_dump($e->getMessage()); die;
        
        } finally {
        
            pg_close($con);
        
        }

    }//end createTableIfNotExist
}
/* End of file CI_hook_log.php */
/* Location: ./application/hooks/CI_hook_log.php */
