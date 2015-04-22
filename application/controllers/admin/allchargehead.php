<?php

class Allchargehead extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('admin/chargehead_model');
        $this->load->library('pagination');
        check_in();
    }

    public function index() {
        $society_data = $this->db->select("id")->where("society_user_id", $this->session->userdata('admin_id'))->get("ci_society")->result();
        $society_id = $society_data[0]->id;

        $data = array();
        $data['charge_head'] = $this->chargehead_model->get_selected_data($society_id);
        $this->load->view('admin/allchargehead', $data);
    }

    public function addchargehead() {
        if ($this->input->post("is_ajax")) {
            $name = $this->input->post("charge_head_name");
            $data = array("charge_head_name" => $name, "added_on" => date("Y-m-d H:i:s"));
            echo ($this->db->insert("ci_chargehead", $data)) ? json_encode(array("name" => $name, "id" => $this->db->insert_id())) : json_encode(array("name" => "", "id" => 0));
        } else {
            $this->load->view("admin/addchargehead");
        }
    }

    public function process() {
        $name = $this->input->post("charge_head_name");

        $data = array("charge_head_name" => $name, "added_on" => date("Y-m-d H:i:s"));
        if ($this->db->insert("ci_chargehead", $data)) {
            $insert_id = $this->db->insert_id();
            $society_data = $this->db->select("id")->where("society_user_id", $this->session->userdata('admin_id'))->get("ci_society")->result();
            $society_id = $society_data[0]->id;
            $this->db->insert("ci_society_chargehead", array("society_id" => $society_id, "chargehead_id" => $insert_id, "added_on" => date("Y-m-d H:i:s")));
            $this->session->set_flashdata('msg_error', "charge Head added successfully.");
        }
        else
            $this->session->set_flashdata('msg_error_red', "charge Head not added successfully.");
        redirect("admin/allchargehead");
    }

    public function editchargehead($id) {
        $name = $this->input->post("charge_head_name");
        if ($this->db->where("id", $id)->update("ci_chargehead", array("charge_head_name" => $name, "updated_on" => date("Y-m-d H:i:s"), "ip" => $_SERVER['REMOTE_ADDR'])))
            echo $name;
        else
            echo "";
    }

    public function deletechargehead($id) {
        if ($this->db->where("id", $id)->delete("ci_chargehead"))
            $this->session->set_flashdata('msg_error', "charge Head deleted successfully.");
        else
            $this->session->set_flashdata('msg_error_red', "charge Head not deleted successfully.");
        redirect("admin/allchargehead");
    }

}