<?php

class Allresidence extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('admin/residence_model');
        $this->load->model('admin/chargehead_model');
        $this->load->library('pagination');
        check_in();
    }

    public function index() {
        if ($this->input->get_post('search_text')) {
            $search_str = "&search_text=" . $this->input->get_post('search_text');
        }
        else
            $search_str = '';


        $config = array();
        $config["base_url"] = base_url() . "admin/allresidence/index/?";
        $config["per_page"] = 10;
        $config["page_query_string"] = true;
        $config["uri_segment"] = 3;
        $config['full_tag_open'] = '<td>';
        $config['first_link'] = 'First';
        $config['last_link'] = 'Last';
        $config['next_link'] = '&gt;';
        $config['prev_link'] = '&lt;';
        $config['cur_tag_open'] = '<b>';
        $config['cur_tag_close'] = '</b>';
        $config['full_tag_close'] = '</td>';

        $page = (isset($_GET['per_page']) && $_GET['per_page'] != '' ) ? $_GET['per_page'] : 0;
        $response = $this->residence_model->all_residence($config["per_page"], $page);
        $data['data'] = $response['rows'];
        $config["total_rows"] = $response['num_rows'];

        $this->pagination->initialize($config);
        $data["links"] = $this->pagination->create_links();

        $this->load->view('admin/allresidence', $data);
    }

    public function editresidence($id = '') {

        $result = $this->residence_model->residence_by_id($id);

        if (count($result) > 0) {
            $data['data'] = $result;
            $udatemail = (isset($result['email'])) ? $result['email'] : "";
            $this->session->set_userdata('update_email', $udatemail);
            $this->load->view('admin/editresidence', $data);
        } else {
            $msg = "Invalid residence id";
            $this->session->set_flashdata('msg_error_red', $msg);
            redirect(base_url() . 'admin/allresidence');
        }
    }

    public function update() {
        $result = $this->residence_model->update_residence();

        if ($result === 1062) {
            $msg = "This email is already used by another user.";

            $this->session->set_flashdata('msg_error_red', $msg);
            redirect(base_url() . 'admin/allresidence');
        }


        $this->residence_model->activity("Update Profile");
        if ($result == true) {
            $this->session->set_flashdata('msg_error', "Residence Updated successfully.");
            redirect(base_url() . 'admin/allresidence');
        } else {
            $this->session->set_flashdata('msg_error_red', "Residence not Updated successfully.");
            redirect(base_url() . 'admin/allresidence');
        }
    }

    public function delete($id) {
        $this->db->where("id", $id)->delete("ci_userpropertys");
        if ($this->db->where("id", $id)->delete("ci_users"))
            $this->session->set_flashdata('msg_error', "Residence deleted successfully.");
        else
            $this->session->set_flashdata('msg_error_red', "Residence not deleted successfully.");
        redirect("admin/allresidence");
    }

    function generatebill() {
        $society_data = $this->db->select("id,society_title")->where("society_user_id", $this->session->userdata('admin_id'))->get("ci_society")->result();
        $society_id = $society_data[0]->id;
        $data['society_name'] = $society_data[0]->society_title;
        $data['charge_head'] = $this->chargehead_model->get_selected_data($society_id);
        $this->load->view('admin/residencebill', $data);
    }

    function downloadbill() {

        $residencedata = $this->residence_model->all_residence();
        $charge_head = $this->input->post("charge_head");
        $society_name = $this->input->post("society_name");
        $data = array();
        $data[0] = array($society_name);
        $data[1] = array("");
        $data[2] = array("Bill Generated On", $this->input->post("bill_generates_on"));
        $data[3] = array("Bill Due Date", $this->input->post("bill_due_on"));
        $data[4] = array("");
        $data[5] = array_merge(array("FLAT", "OWNER", "EMAIL"), array_map("strtoupper", $charge_head), array("TAX", "TOTAL"));
        $i = 6;
        foreach ($residencedata['rows'] as $val) {
            $data[$i] = array($val["address"], $val["fname"] . " " . $val["lname"], $val["email"]);
            $i++;
        }

        $headers = $data;
        $this->session->set_userdata("bill_header", json_encode($headers));
        $this->session->set_userdata("bill_file_name", $society_name . "_Bill_".$this->input->post("bill_generates_on").".csv");
        redirect("admin/allresidence/uploadbill");
    }

    function uploadbill() {

        if ($this->session->userdata("bill_header") && $this->session->userdata("bill_file_name")) {
            $this->load->helper('csv');
            $bill_header = $this->session->userdata("bill_header");
            $bill_file_name = $this->session->userdata("bill_file_name");
            $this->session->unset_userdata(array("bill_header" => "", "bill_file_name" => ""));
            header("refresh:;url=uploadbill");
            echo array_to_csv(json_decode($bill_header, true), $bill_file_name);
        } else {
            $this->load->view('admin/uploadresidencebill');
        }
    }

    function billpreview() {
        $this->load->helper("inflector");
        if ($_FILES) {
            $file = $_FILES['bill_data']['tmp_name'];
            $handle = fopen($file, "r");
            $insert_data = array();
            $i = 0;
            $success_data = array();
            $failure_data = array();
            $start_date = "";
            $end_date = "";
            while ($post_data = fgetcsv($handle, 1000, ",", "'")) {
                $post_data = array_map("trim", $post_data);
                if ($i == 2) {
                    $start_date = $post_data[1];
                } else if ($i == 3) {
                    $end_date = $post_data[1];
                } else if ($i == 5) {
                    $key_array = array_map("strtolower", $post_data);
                    unset($key_array[0]);
                    unset($key_array[1]);
                    unset($key_array[2]);
                }
                if ($i < 6) {
                    $i++;
                    continue;
                }
                $selected_charge_head = array();
                $total = 0;
                $success = 1;
                $tax = "";
                for ($_k = 3; $_k < count($post_data) - 1; $_k++) {
                    if ($key_array[$_k] == "tax") {
                        $tax = $post_data[$_k];
                    } else {
                        $selected_charge_head[strtolower($key_array[$_k])] = $post_data[$_k];
                        if ($post_data[$_k] == "") {
                            $success = 0;
                        }
                    }
                    $total += $post_data[$_k];
                }
                $extra_data = array("address" => $post_data[0], "name" => $post_data[1], "email" => $post_data[2]);
                if ($success) {
                    $success_data[] = array_merge($extra_data, $selected_charge_head, array("Tax" => $tax, "TOTAL" => $total));
                    $success_post_data[] = array("data" => array_merge($extra_data, array("sdate" => $start_date, "edate" => $end_date, "tax" => $tax, "total" => $total)), "charge_head" => $selected_charge_head);
                }
                else
                    $failure_data[] = array_merge($extra_data, $selected_charge_head, array("Tax" => $tax, "TOTAL" => $total));
            }
            if (!empty($success_data) || !empty($failure_data)) {
                $data['header'] = !empty($success_data) ? array_keys($success_data[0]) : array_keys($failure_data[0]);
                $data['header'][0] = "Flat";
                $data['success_data'] = $success_data;
                $data['success_post_data'] = $success_post_data;
                $data['failure_data'] = $failure_data;
                $this->load->view('admin/billresult', $data);
            }
        } else {
            redirect("admin/allresidence/uploadbill");
        }
    }

    public function processbill() {
        if (!$this->input->post("success_data"))
            redirect("admin/allresidence/uploadbill");
        $this->load->model("chargehead_model");
        $success_data = json_decode($this->input->post("success_data"), true);
        $society_data = $this->db->select("id,society_title")->where("society_user_id", $this->session->userdata('admin_id'))->get("ci_society")->result();
        $society_id = $society_data[0]->id;
        $response = $this->chargehead_model->get_selected_data($society_id);
        foreach ($response as $val) {
            $chargehead_data[strtolower($val->charge_head_name)] = $val->chargehead_id;
        }
        $insert_data = array();
        $email_data = array();
        foreach ($success_data as $val) {
            $email_data[] = array_merge($val['data'], array("charge_head" => $val['charge_head']));
            $property_id = $this->residence_model->get_property_id($society_id, $val['data']['address'], $val['data']['email']);
            $related_id = 0;
            $i = 0;
            foreach ($val['charge_head'] as $_k => $_v) {
                $_temp = array(
                    "society_id" => $society_id,
                    "property_id" => $property_id,
                    "sdate" => $val['data']['sdate'],
                    "edate" => $val['data']['edate'],
                    "bill_id" => $chargehead_data[strtolower($_k)],
                    "amount" => $_v,
                    "taxamount" => $val['data']['tax'],
                    "totalamount" => $val['data']['total'],
                    "timestamp" => date("Y-m-d H:i:s"),
                    "addbyid" => $this->session->userdata('admin_id'),
                    "status" => "1",
                    "related_id" => $related_id
                );
                if ($i == 0) {
                    $this->db->insert("ci_bill_charge", $_temp);
                    $related_id = $this->db->insert_id();
                    $this->db->where("id", $related_id)->update("ci_bill_charge", array("related_id" => $related_id));
                } else {
                    $insert_data[] = $_temp;
                }
                $i++;
            }
        }
        if (!empty($insert_data)) {
            $this->db->insert_batch("ci_bill_charge", $insert_data);
            $this->residence_model->send_mail($email_data);
            $this->session->set_flashdata('msg_error', "Bill generated successfully.");
            redirect("admin/login/dashboard/?&bill=details");
        }
    }

    function bill_detail($id) {
        $response = $this->residence_model->billdetailbyid($id);
        $data['data'] = $response[0];
        $this->load->view("admin/bill_details", $data);
    }

}