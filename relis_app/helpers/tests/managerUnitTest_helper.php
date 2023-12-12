<?php
/////////////////////////////////////// NEW ////////////////////////////////////

// TEST MANAGER CONTROLLER
class ManagerUnitTest
{
    private $controller;
    private $http_client;
    private $ci;

    function __construct()
    {
        $this->controller = "manager";
        $this->http_client = new Http_client();
        $this->ci = get_instance();
    }

    function run_tests()
    {
        // $this->TestInitialize(); ///////////////////////////
        // $this->listUsergroup();
        $this->list();
    }

    private function TestInitialize()
    {
        //delete generated userdata session files
        deleteSessionFiles();
        //delete created test user
        deleteCreatedTestUser();
        //delete created test Project
        deleteCreatedTestProject();
        //Login as admin
        $this->http_client->response("user", "check_form", ['user_username' => 'admin', 'user_password' => '123'], "POST");
        //create test user
        addTestUser();
        createDemoProject();
        //add 5 papers to test Project
        addBibtextPapersToProject("relis_app/helpers/tests/testFiles/paper/5_bibPapers.bib");
        //add users to test Project
        addUserToProject(getAdminUserId(), "Reviewer");
        addUserToProject(getTestUserId(), "Reviewer");
        //perform screening with 4 paper inclusions
        assignPapers_and_performScreening([getAdminUserId()], 'Title', -1, 4);
        //perform QA (2 high quality QAs, 2 low quality QAs)
        $this->qa_results = assignPapers_and_performQA([getAdminUserId()], 4, 2);
        //Exclude low quality papers
        qaExcludeLowQuality();
        //perform classification
        assignPapersForClassification([getAdminUserId(), getTestUserId()]);
        performClassification();
    }

    /*
     * Test 1
     * Action : entity_list
     * Description : display the list of usergroups
     * Expected value: check if the correct elements are displayed
     */
    private function listUsergroup()
    {
        $action = "entity_list";
        $test_name = "display the list of usergroups";
        $test_aspect = "Correct element(s) displayed?";
        $expected_value = "Yes";

        $response = $this->http_client->response($this->controller, $action . "/usergroup");

        //follow redirect
        while (in_array($response['status_code'], [http_code()[301], http_code()[302], http_code()[303], http_code()[307]])) {
            $response = $this->http_client->response($this->http_client->getShortUrl($response['url']), "");
        }

        if ($response['status_code'] != http_code()[200]) {
            $actual_value = "<span style='color:red'>" . $response['content'] . "</span>";
        } else {
            $actual_value = "No";

            //get all entries in the db
            $data = $this->ci->db->query("SELECT * FROM usergroup")->result_array();

            //check if all entries are listed
            $entriesListed = [];
            foreach ($data as $dt) {
                if (strstr($response['content'], $dt['usergroup_name']) != false) {
                    array_push($entriesListed, $dt);
                }
            }
            if (count($entriesListed) == count($data)) {
                $actual_value = "Yes";
            }
        }

        run_test($this->controller, $action, $test_name, $test_aspect, $expected_value, $actual_value);
    }

    /*
     * Test 2 ////////////////////////////////
     * Action : entity_list
     * Description : display the list of usergroups /////////////////////////
     * Expected value: check if the correct elements are displayed
     */
    private function list() ////////////////////////
    {
        $urlsString = "";

        $urls = findUrlWithWord("relis_app/helpers/tests/testFiles/demoDraft/draft.html", ['element', 'manager']);

        foreach ($urls as $url) {
            $urlsString = $urlsString . "<br>" . $url;
        }

        run_test("", "", "", "", "", $urlsString);
    }


    private $element = [
        'users',
        'new_users',
        'user_creation',
        'usergroup',
        'project',
        'user_project',
        'config_admin',
        'config',
        'exclusioncrieria',
        'inclusioncriteria',
        'research_question',
        'affiliation',
        'papers_sources',
        'search_strategy',
        'papers',
        'author',
        'paper_author',
        'venue',
        'screen_phase',
        'screening',
        'screen_decison',
        'logs',
        'info',
        'str_mng',
        'operations',
        'qa_questions',
        'qa_responses',
        'qa_result',
        'qa_assignment',
        'qa_validation_assignment',
        'assignation',
        'debug',
        'exclusion',
        'inclusion'
    ]; /////////////////////////////////




    /*
     * Fonction globale pour afficher la liste des élément suivant la structure de la table
     *
     * Input: $ref_table: nom de la configuration d'une page (ex papers, author)
     * 			$val : valeur de recherche si une recherche a été faite sur la table en cours
     * 			$page: la page affiché : ulilisé dans la navigation
     */
    public function entity_list($ref_table, $val = "_", $page = 0, $dynamic_table = 0)
    {
        /*
         * Vérification si il y a une condition de recherche
         */
        $val = urldecode(urldecode($val));
        $filter = array();
        if (isset($_POST['search_all'])) {
            $filter = $this->input->post();
            unset($filter['search_all']);
            $val = "_";
            if (isset($filter['valeur']) and !empty($filter['valeur'])) {
                $val = $filter['valeur'];
                $val = urlencode(urlencode($val));
            }
            /*
             * mis à jours de l'url en ajoutant la valeur recherché dans le lien puis rechargement de l'url
             */
            $url = "manager/entity_list/" . $ref_table . "/" . $val . "/0/";
            redirect($url);
        }
        /*
         * Récupération de la configuration(structure) de la table à afficher
         */
        $ref_table_config = get_table_config($ref_table);
        $table_id = $ref_table_config['table_id'];
        /*
         * Appel du model pour récupérer la liste à aficher dans la Base de donnés
         */
        $rec_per_page = ($dynamic_table) ? -1 : 0;
        if ($ref_table == "str_mng") { //pour le string_management une fonction spéciale
            //todo verifier comment le spécifier dans config
            $data = $this->DBConnection_mdl->get_list_str_mng($ref_table_config, $val, $page, $rec_per_page, $this->session->userdata('active_language'));
        } else {
            $data = $this->DBConnection_mdl->get_list($ref_table_config, $val, $page, $rec_per_page);
        }
        //print_test($data);
        /*
         * récupération des correspondances des clés externes pour l'affichage  suivant la structure de la table
         */
        $dropoboxes = array();
        foreach ($ref_table_config['fields'] as $k => $v) {
            if (!empty($v['input_type']) and $v['input_type'] == 'select' and $v['on_list'] == 'show') {
                if ($v['input_select_source'] == 'array') {
                    $dropoboxes[$k] = $v['input_select_values'];
                } elseif ($v['input_select_source'] == 'table') {
                    //print_test($v);
                    $dropoboxes[$k] = $this->manager_lib->get_reference_select_values($v['input_select_values']);
                } elseif ($v['input_select_source'] == 'yes_no') {
                    $dropoboxes[$k] = array(
                        '0' => "No",
                        '1' => "Yes"
                    );
                }
            }
        }
        /*
         * Vérification des liens (links) a afficher sur la liste
         */
        $list_links = array();
        $add_link = false;
        $add_link_url = "";
        foreach ($ref_table_config['links'] as $link_type => $link) {
            if (!empty($link['on_list'])) { {
                    $link['type'] = $link_type;
                    if (empty($link['title'])) {
                        $link['title'] = lng_min($link['label']);
                    }
                    $push_link = false;
                    switch ($link_type) {
                        case 'add':
                            $add_link = true; //will appear as a top button
                            if (empty($link['url']))
                                $add_link_url = 'manager/add_element/' . $ref_table;
                            else
                                $add_link_url = $link['url'];
                            break;
                        case 'view':
                            if (!isset($link['icon']))
                                $link['icon'] = 'folder';
                            if (empty($link['url']))
                                $link['url'] = 'manager/display_element/' . $ref_table . '/';
                            $push_link = true;
                            break;
                        case 'edit':
                            if (!isset($link['icon']))
                                $link['icon'] = 'pencil';
                            if (empty($link['url']))
                                $link['url'] = 'manager/edit_element/' . $ref_table . '/';
                            $push_link = true;
                            break;
                        case 'delete':
                            if (!isset($link['icon']))
                                $link['icon'] = 'trash';
                            if (empty($link['url']))
                                $link['url'] = 'manager/delete_element/' . $ref_table . '/';
                            $push_link = true;
                            break;
                        case 'add_child':
                            if (!isset($link['icon']))
                                $link['icon'] = 'plus';
                            if (!empty($link['url'])) {
                                $link['url'] = 'manager/add_element_child/' . $link['url'] . "/" . $ref_table . "/";
                                $push_link = true;
                            }
                            break;
                        default:
                            break;
                    }
                    if ($push_link)
                        array_push($list_links, $link);
                }
            }
        }
        /*
         * Préparation de la liste à afficher sur base du contenu et  stucture de la table
         */
        /**
         * @var array $field_list va contenir les champs à afficher 
         */
        $field_list = array();
        $field_list_header = array();
        foreach ($ref_table_config['fields'] as $k => $v) {
            if ($v['on_list'] == 'show') {
                array_push($field_list, $k);
                array_push($field_list_header, $v['field_title']);
            }
        }
        //print_test($field_list);
        $i = 1;
        $list_to_display = array();
        foreach ($data['list'] as $key => $value) {
            $element_array = array();
            foreach ($field_list as $key_field => $v_field) {
                if (isset($value[$v_field])) {
                    if (isset($dropoboxes[$v_field][$value[$v_field]])) {
                        $element_array[$v_field] = $dropoboxes[$v_field][$value[$v_field]];
                    } else {
                        $element_array[$v_field] = $value[$v_field];
                    }
                } else {
                    $element_array[$v_field] = "";
                    if (isset($ref_table_config['fields'][$v_field]['number_of_values']) and $ref_table_config['fields'][$v_field]['number_of_values'] != 1) {
                        if (isset($ref_table_config['fields'][$v_field]['input_select_values']) and isset($ref_table_config['fields'][$v_field]['input_select_key_field'])) {
                            // récuperations des valeurs de cet element
                            $M_values = $this->manager_lib->get_element_multi_values($ref_table_config['fields'][$v_field]['input_select_values'], $ref_table_config['fields'][$v_field]['input_select_key_field'], $data['list'][$key][$table_id]);
                            $S_values = "";
                            foreach ($M_values as $k_m => $v_m) {
                                if (isset($dropoboxes[$v_field][$v_m])) {
                                    $M_values[$k_m] = $dropoboxes[$v_field][$v_m];
                                }
                                $S_values .= empty($S_values) ? $M_values[$k_m] : " | " . $M_values[$k_m];
                            }
                            $element_array[$v_field] = $S_values;
                        }
                    }
                }
            }
            /*
             * Ajout des liens(links) sur la liste
             */
            $action_button = "";
            $arr_buttons = array();
            foreach ($list_links as $key_l => $value_l) {
                if (!empty($value_l['icon']))
                    $value_l['label'] = icon($value_l['icon']) . ' ' . lng_min($value_l['label']);
                array_push(
                    $arr_buttons,
                    array(
                        'url' => $value_l['url'] . $value[$table_id],
                        'label' => $value_l['label'],
                        'title' => $value_l['title']
                    )
                );
            }
            $action_button = create_button_link_dropdown($arr_buttons, lng_min('Action'));
            if (!empty($action_button))
                $element_array['links'] = $action_button;
            if (isset($element_array[$table_id])) {
                $element_array[$table_id] = $i + $page;
            }
            array_push($list_to_display, $element_array);
            $i++;
        }
        $data['list'] = $list_to_display;
        /*
         * Ajout de l'entête de la liste
         */
        if (!empty($data['list'])) {
            //$array_header=$ref_table_config['header_list_fields'];
            $array_header = $field_list_header;
            if (!empty($data['list'][$key]['links'])) {
                array_push($array_header, '');
            }
            if (!$dynamic_table) {
                array_unshift($data['list'], $array_header);
            } else {
                $data['list_header'] = $array_header;
            }
        }
        /*
         * Création des boutons qui vont s'afficher en haut de la page (top_buttons)
         */
        $data['top_buttons'] = "";
        if ($ref_table == "str_mng") { //todo à corriger
            if ($this->session->userdata('language_edit_mode') == 'yes') {
                $data['top_buttons'] .= get_top_button('all', 'Close edition mode', 'config/update_edition_mode/no', 'Close edition mode', 'fa-ban', '', ' btn-warning ');
            } else {
                $data['top_buttons'] .= get_top_button('all', 'Open edition mode', 'config/update_edition_mode/yes', 'Open edition mode', 'fa-check', '', ' btn-dark ');
            }
        } else {
            if ($add_link)
                $data['top_buttons'] .= get_top_button('add', 'Add new', $add_link_url);
        }
        if (activate_update_stored_procedure())
            $data['top_buttons'] .= get_top_button('all', 'Update stored procedure', 'home/update_stored_procedure/' . $ref_table, 'Update stored procedure', 'fa-check', '', ' btn-dark ');
        if ($this->session->userdata('working_perspective') == 'class') {
            $data['top_buttons'] .= get_top_button('close', 'Close', 'home');
        } else {
            $data['top_buttons'] .= get_top_button('close', 'Close', 'screening/screening');
        }
        /*
         * Titre de la page
         */
        if (isset($ref_table_config['entity_title']['list'])) {
            $data['page_title'] = lng($ref_table_config['entity_title']['list']);
        } else {
            $data['page_title'] = lng("List of " . $ref_table_config['reference_title']);
        }
        /*
         * Configuration pour l'affichage des lien de navigation
         */
        $data['valeur'] = ($val == "_") ? "" : $val;
        /*
         * Si on a besoin de faire urecherche sur la liste specifier la vue où se trouve le formulaire de recherche
         */
        if (!$dynamic_table and !empty($ref_table_config['search_by'])) {
            $data['search_view'] = 'general/search_view';
        }
        /*
         * La vue qui va s'afficher
         */
        if (!$dynamic_table) {
            $data['nav_pre_link'] = 'manager/entity_list/' . $ref_table . '/' . $val . '/';
            $data['nav_page_position'] = 5;
            $data['page'] = 'general/list';
        } else {
            $data['page'] = 'general/list_dt';
        }
        if (admin_config($ref_table))
            $data['left_menu_admin'] = True;
        //print_test($data);
        /*
         * Chargement de la vue avec les données préparés dans le controleur
         */
        $this->load->view('shared/body', $data);
    }

}