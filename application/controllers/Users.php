<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Users extends MY_Controller
{


	public function __construct()
	{
		parent::__construct();

		$this->load->language('settings');
		$this->load->language('users');
		$this->load->language('users_import');

		$this->require_logged_in();
		$this->require_auth_level(ADMINISTRATOR);

		$this->load->model('users_model');
		$this->load->model('departments_model');
		$this->load->helper('number');
		$this->load->helper('user');
		$this->load->helper('string');

		$this->data['max_size_bytes'] = max_upload_file_size();
		$this->data['max_size_human'] = byte_format(max_upload_file_size());
	}


	/**
	* Users index page
	*
	*/
	function index($page = 0)
	{
		// Cleanup import-related files if necessary
		$this->cleanup_import();

		$this->data['menu_active'] = 'settings/users';
		$this->data['breadcrumbs'][] = array('settings', lang('settings_page_title'));
		$this->data['breadcrumbs'][] = array('users', lang('users_page_index'));

		$this->data['title'] = lang('users_page_index');

		if ( ! isset($_GET['limit'])) {
			$_GET['limit'] = 10;
		} else {
			$_GET['limit'] = (int) $_GET['limit'];
		}

		$filter = $this->input->get();
		$filter['sort'] = 'username';
		$filter['limit'] = 10;
		$filter['offset'] = $page;

		$this->data['filter'] = $filter;
		$this->data['total'] = $this->users_model->count($filter);
		$this->data['users'] = $this->users_model->find($filter);

		$pagination_config = [
			'base_url' => site_url('users/index'),
			'total_rows' => $this->data['total'],
			'per_page' => $filter['limit'],
			'reuse_query_string' => TRUE,
		];
		$this->load->library('pagination');
		$this->pagination->initialize(pagination_config($pagination_config));

		$this->blocks['tabs'] = 'users/menu';

		$this->render('users/index');
	}


	/**
	 * View summary of user account.
	 *
	 * @param integer $id		ID of user to view
	 *
	 */
	public function view($id = 0)
	{
		$user = $this->find_user($id);

		$this->data['user'] = $user;

		$this->data['menu_active'] = 'settings/users/view';
		$this->data['breadcrumbs'][] = array('settings', lang('settings_page_title'));
		$this->data['breadcrumbs'][] = array('users', lang('users_page_index'));
		$this->data['breadcrumbs'][] = array('users/view/' . $id, html_escape($user->username));

		$this->data['title'] = html_escape($user->username);

		$this->blocks['tabs'] = 'users/context/menu';

		$this->render('users/view');
	}


	/**
	 * Add a new user account.
	 *
	 */
	public function add()
	{
		$this->data['user'] = NULL;

		$this->data['menu_active'] = 'settings/users/add';
		$this->data['breadcrumbs'][] = array('settings', lang('settings_page_title'));
		$this->data['breadcrumbs'][] = array('users', lang('users_page_index'));
		$this->data['breadcrumbs'][] = array('users/add', lang('users_add_page_title'));

		$this->init_form_elements();

		$this->data['title'] = lang('users_add_page_title');

		$this->data['menu_active'] = 'settings/users/add';
		$this->blocks['tabs'] = 'users/menu';

		if ($this->input->post()) {
			$this->save_user();
		}

		$this->render('users/update');
	}


	/**
	 * Update a user account
	 *
	 * @param int $id		ID of user to update
	 *
	 */
	public function update($id = 0)
	{
		$user = $this->find_user($id);

		$this->data['user'] = $user;

		$this->data['menu_active'] = 'settings/users/update';
		$this->data['breadcrumbs'][] = array('settings', lang('settings_page_title'));
		$this->data['breadcrumbs'][] = array('users', lang('users_page_index'));
		$this->data['breadcrumbs'][] = array('users/view/' . $id, html_escape($user->username));
		$this->data['breadcrumbs'][] = array('users/update/' . $id, lang('users_update_page_title'));

		$this->init_form_elements();

		$this->data['title'] = html_escape($user->username) . ': ' . lang('users_update_page_title');

		$this->blocks['tabs'] = 'users/context/menu';

		if ($this->input->post()) {
			$this->save_user($user);
		}

		$this->render('users/update');
	}


	/**
	 * Change Password page for given user.
	 *
	 * @param  int $id		ID of user to change password for.
	 *
	 */
	public function change_password($id = 0)
	{
		$user = $this->find_user($id);

		$this->data['user'] = $user;

		$this->data['menu_active'] = 'settings/users/password';
		$this->data['breadcrumbs'][] = array('settings', lang('settings_page_title'));
		$this->data['breadcrumbs'][] = array('users', lang('users_page_index'));
		$this->data['breadcrumbs'][] = array('users/view/' . $id, html_escape($user->username));
		$this->data['breadcrumbs'][] = array('users/change_password/' . $id, lang('users_change_password_page_title'));

		$this->init_form_elements();

		$this->data['title'] = html_escape($user->username) . ': ' . lang('users_change_password_page_title');

		$this->blocks['tabs'] = 'users/context/menu';

		if ($this->input->post()) {
			$this->save_password($user);
		}

		$this->render('users/password');
	}



	/**
	 * Delete user account
	 *
	 * @param integer $id		ID of user to delete
	 *
	 */
	public function delete($id = 0)
	{
		$user = $this->find_user($id);

		$this->data['user'] = $user;

		$this->data['menu_active'] = 'settings/users/delete';
		$this->data['breadcrumbs'][] = array('settings', lang('settings_page_title'));
		$this->data['breadcrumbs'][] = array('users', lang('users_page_index'));
		$this->data['breadcrumbs'][] = array('users/view/' . $id, html_escape($user->username));
		$this->data['breadcrumbs'][] = array('users/delete/' . $id, lang('users_delete_page_title'));

		$this->init_form_elements();

		$this->data['title'] = html_escape($user->username) . ': ' . lang('users_delete_page_title');

		$this->blocks['tabs'] = 'users/context/menu';

		if ($this->input->post('user_id') == $user->user_id && $this->input->post('action') == 'delete') {

			$res = $this->users_model->delete(['user_id' => $user->user_id]);
			$success = FALSE;

			if ($res) {
				$this->notice('success', lang('users_delete_status_success'), [
					'username' => $user->username,
				]);
			} else {
				$this->notice('error', lang('users_delete_status_error'));
			}

			return redirect("users");
		}

		$this->render('users/delete');
	}


	/**
	 * Save changes to user: update or add new
	 *
	 * @param $user		User object if updating, NULL to add new user.
	 *
	 */
	private function save_user($user = NULL)
	{
		$this->load->library('form_validation');

		$this->load->config('form_validation', TRUE);
		$this->form_validation->set_rules($this->config->item('user_details', 'form_validation'));

		if ($user) {
			// Update
			$this->form_validation->set_rules('username', "lang:user_field_username", "required|trim|max_length[64]|user_username_unique[{$user->user_id}]");
			$this->form_validation->set_rules('email', "lang:user_field_email", "trim|max_length[255]|valid_email|user_email_unique[{$user->user_id}]");
		} else {
			// Add
			$this->form_validation->set_rules('username', "lang:user_field_username", "required|trim|max_length[64]|user_username_unique");
			$this->form_validation->set_rules('email', "lang:user_field_email", "trim|max_length[255]|valid_email|user_email_unique");
			$this->form_validation->set_rules('set_password_1', 'lang:user_field_set_password_1', 'required|trim|min_length[8]');
			$this->form_validation->set_rules('set_password_2', 'lang:user_field_set_password_2', 'matches[set_password_1]');
		}

		if ($this->form_validation->run() == FALSE) {
			$this->notice('error', lang('error_form_validation'));
			return;
		}

		$keys = [
			'username',
			'authlevel',
			'enabled',
			'email',
			'department_id',
			'firstname',
			'lastname',
			'displayname',
			'ext',
			'set_password_1',
		];

		$user_data = array_fill_safe($keys, $this->input->post());
		$success = FALSE;

		if ($user !== NULL) {

			// Update user
			$res = $this->users_model->update($user_data, ['user_id' => $user->user_id]);
			$id = $user->user_id;

			if ($res) {
				$success = TRUE;
				$this->notice('success', lang('users_update_status_success'));
			} else {
				$this->notice('error', lang('users_update_status_error'));
			}


		} else {

			// Add new user
			$res = $this->users_model->insert($user_data);
			$id = $res;

			if ($res) {

				$success = TRUE;

				$this->notice('success', lang('users_add_status_success'), [
					'username' => $user_data['username'],
					'password' => $user_data['set_password_1'],
				]);

			} else {
				$this->notice('error', lang('users_add_status_error'));
			}


		}

		if ($success) {
			redirect("users/view/{$id}");
		}
	}


	/**
	 * Save new password (Change password page)
	 *
	 * @param $user		User object to update
	 *
	 */
	private function save_password($user)
	{
		$this->load->library('form_validation');

		$this->load->config('form_validation', TRUE);
		$this->form_validation->set_rules($this->config->item('user_password', 'form_validation'));

		if ($this->form_validation->run() == FALSE) {
			$this->notice('error', lang('error_form_validation'));
			return;
		}

		$keys = [
			'new_password_1',
		];

		$user_data = array_fill_safe($keys, $this->input->post());
		$success = FALSE;

		// Update user
		$res = $this->users_model->update($user_data, ['user_id' => $user->user_id]);
		$id = $user->user_id;

		if ($res) {
			$success = TRUE;
			$this->notice('success', lang('users_change_password_status_success'), [
				'password' => $user_data['new_password_1'],
			]);
		} else {
			$this->notice('error', lang('users_change_password_status_error'));
		}

		if ($success) {
			redirect("users/view/{$id}");
		}
	}



	private function find_user($id = 0)
	{
		$user = $this->users_model->find_one([
			'user_id' => $id,
			'include' => ['department'],
		]);

		if ( ! $user) {
			$this->render_error(array(
				'http' => 404,
				'title' => 'Not found',
				'description' => lang('users_not_found'),
			));
		}

		return $user;
	}


	private function init_form_elements()
	{
		$this->data['authlevel_options'] = [
			ADMINISTRATOR => lang('user_authlevel_administrator'),
			TEACHER => lang('user_authlevel_teacher'),
		];

		$departments = $this->departments_model->find([
			'sort' => 'name',
			'limit' => NULL,
		]);

		$this->data['departments'] = results_dropdown('department_id', 'name', $departments, '(None)');
	}



	// legacy



	/**
	 * User account listing
	 *
	 */
	function _old_index($page = NULL)
	{
		// Cleanup import-related files if necessary
		$this->cleanup_import();

		$pagination_config = array(
			'base_url' => site_url('users/index'),
			'total_rows' => $this->crud_model->Count('users'),
			'per_page' => 25,
			'full_tag_open' => '<p class="pagination">',
			'full_tag_close' => '</p>',
		);

		$this->load->library('pagination');
		$this->pagination->initialize($pagination_config);

		$this->data['pagelinks'] = $this->pagination->create_links();
		$this->data['users'] = $this->users_model->Get(NULL, $pagination_config['per_page'], $page);

		$this->data['title'] = 'Manage Users';
		$this->data['showtitle'] = $this->data['title'];
		$this->data['body'] = $this->load->view('users/users_index', $this->data, TRUE);

		return $this->render();
	}




	/**
	 * Add a new user
	 *
	 */
	function _add()
	{
		$this->data['departments'] = $this->departments_model->Get();

		$this->data['title'] = 'Add User';
		$this->data['showtitle'] = $this->data['title'];

		$columns = array(
			'c1' => array(
				'content' => $this->load->view('users/users_add', $this->data, TRUE),
				'width' => '70%',
			),
			'c2' => array(
				'content' => $this->load->view('users/users_add_side', $this->data, TRUE),
				'width' => '30%',
			),
		);

		$this->data['body'] = $this->load->view('columns', $columns, TRUE);

		return $this->render();
	}




	/**
	 * Edit user account
	 *
	 */
	function _edit($id = NULL)
	{
		$this->data['user'] = $this->users_model->Get($id);

		if (empty($this->data['user'])) {
			show_404();
		}

		$this->data['departments'] = $this->departments_model->Get();

		$this->data['title'] = 'Edit User';
		$this->data['showtitle'] = $this->data['title'];

		$columns = array(
			'c1' => array(
				'content' => $this->load->view('users/users_add', $this->data, TRUE),
				'width' => '70%',
			),
			'c2' => array(
				'content' => $this->load->view('users/users_add_side', $this->data, TRUE),
				'width' => '30%',
			),
		);

		$this->data['body'] = $this->load->view('columns', $columns, TRUE);

		return $this->render();
	}





	/**
	 * Save user details
	 *
	 */
	function _ave()
	{
		$user_id = $this->input->post('user_id');

		$this->load->library('form_validation');

		$this->form_validation->set_rules('user_id', 'ID', 'integer');
		$this->form_validation->set_rules('username', 'Username', 'required|max_length[20]');
		$this->form_validation->set_rules('authlevel', 'Type', 'required|integer');
		$this->form_validation->set_rules('enabled', 'Enabled', 'required|integer');
		$this->form_validation->set_rules('email', 'Email address', 'valid_email|max_length[255]');

		if (empty($user_id)) {
			$this->form_validation->set_rules('password1', 'Password', 'trim|required');
			$this->form_validation->set_rules('password2', 'Password (confirm)', 'trim|matches[password1]');
		} else {
			if ($this->input->post('password1')) {
				$this->form_validation->set_rules('password1', 'Password', 'trim');
				$this->form_validation->set_rules('password2', 'Password (confirm)', 'trim|matches[password1]');
			}
		}

		$this->form_validation->set_rules('firstname', 'First name', 'max_length[20]');
		$this->form_validation->set_rules('lastname', 'Last name', 'max_length[20]');
		$this->form_validation->set_rules('displayname', 'Display name', 'max_length[20]');
		$this->form_validation->set_rules('department_id', 'Department', 'integer');
		$this->form_validation->set_rules('ext', 'Extension', 'max_length[10]');

		if ($this->form_validation->run() == FALSE) {
			return (empty($user_id) ? $this->add() : $this->edit($user_id));
		}

		$user_data = array(
			'username' => $this->input->post('username'),
			'authlevel' => $this->input->post('authlevel'),
			'enabled' => $this->input->post('enabled'),
			'email' => $this->input->post('email'),
			'firstname' => $this->input->post('firstname'),
			'lastname' => $this->input->post('lastname'),
			'displayname' => $this->input->post('displayname'),
			'department_id' => $this->input->post('department_id'),
			'ext' => $this->input->post('ext'),
		);

		if ($this->input->post('password1') && $this->input->post('password2')) {
			$user_data['password'] = password_hash($this->input->post('password1'), PASSWORD_DEFAULT);
		}

		if (empty($user_id)) {

			$user_id = $this->users_model->Add($user_data);

			if ($user_id) {
				$line = sprintf($this->lang->line('crbs_action_added'), $user_data['username']);
				$flashmsg = msgbox('info', $line);
			} else {
				$line = sprintf($this->lang->line('crbs_action_dberror'), 'adding');
				$flashmsg = msgbox('error', $line);
			}

		} else {

			if ($this->users_model->Edit($user_id, $user_data)) {
				$line = sprintf($this->lang->line('crbs_action_saved'), $user_data['username']);
				$flashmsg = msgbox('info', $line);
			} else {
				$line = sprintf($this->lang->line('crbs_action_dberror'), 'editing');
				$flashmsg = msgbox('error', $line);
			}

		}

		$this->session->set_flashdata('saved', $flashmsg);
		redirect('users');
	}





	/**
	 * Delete a user
	 *
	 */
	function _delete($id = NULL)
	{
		if ($this->input->post('id')) {
			$ret = $this->users_model->Delete($this->input->post('id'));
			$flashmsg = msgbox('info', $this->lang->line('crbs_action_deleted'));
			$this->session->set_flashdata('saved', $flashmsg);
			return redirect('users');
		}

		if ($id == $_SESSION['user_id']) {
			$flashmsg = msgbox('error', "You cannot delete your own user account.");
			$this->session->set_flashdata('saved', $flashmsg);
			return redirect('users');
		}

		$this->data['action'] = 'users/delete';
		$this->data['id'] = $id;
		$this->data['cancel'] = 'users';
		$this->data['text'] = 'If you delete this user, all of their past and future bookings will also be deleted, and their rooms will no longer be owned by them.';

		$row = $this->users_model->Get($id);

		$this->data['title'] = 'Delete User ('.html_escape($row->username).')';
		$this->data['showtitle'] = $this->data['title'];
		$this->data['body'] = $this->load->view('partials/deleteconfirm', $this->data, TRUE);

		return $this->render();
	}




	/**
	 * First page of import.
	 * If GET, show the form. If POST, handle CSV upload + import.
	 *
	 */
	public function _import()
	{
		if ($this->input->post('action') == 'import') {
			$this->process_import();
		}

		$this->cleanup_import();

		$this->data['title'] = 'Import Users';
		$this->data['showtitle'] = $this->data['title'];
		// $this->data['body'] = $this->load->view('users/import/stage1', NULL, TRUE);

		$columns = array(
			'c1' => array(
				'content' => $this->load->view('users/import/stage1', $this->data, TRUE),
				'width' => '50%',
			),
			'c2' => array(
				'content' => $this->load->view('users/import/stage1_side', $this->data, TRUE),
				'width' => '50%',
			),
		);

		$this->data['body'] = $this->load->view('columns', $columns, TRUE);

		return $this->render();
	}




	/**
	 * Show the results of the import.
	 *
	 * The results are stored in a temporary file, the filename
	 * of which is stored in the session.
	 *
	 */
	public function _import_results()
	{
		if ( ! array_key_exists('import_results', $_SESSION)) {
			$flashmsg = msgbox('error', "No import data found.");
			$this->session->set_flashdata('saved', $flashmsg);
			return redirect('users/import');
		}

		$filename = $_SESSION['import_results'];
		if ( ! is_file(FCPATH . "local/{$filename}")) {
			$flashmsg = msgbox('error', "Import results file not found.");
			$this->session->set_flashdata('saved', $flashmsg);
			return redirect('users/import');
		}

		$raw = @file_get_contents(FCPATH . "local/{$filename}");
		$result = json_decode($raw);

		$this->data['result'] = $result;

		$this->data['title'] = 'Imported Users';
		$this->data['showtitle'] = $this->data['title'];
		$this->data['body'] = $this->load->view('users/import/stage2', $this->data, TRUE);

		return $this->render();
	}




	/**
	 * When the CSV form is submitted, this is called to handle the file
	 * and process the lines.
	 *
	 */
	private function _process_import()
	{
		$has_csv = (isset($_FILES['userfile'])
		              && isset($_FILES['userfile']['name'])
		              && ! empty($_FILES['userfile']['name']));

		if ( ! $has_csv) {
			$notice = msgbox('exclamation', "No CSV file uploaded");
			$this->data['notice'] = $notice;
			return FALSE;
		}

		$this->load->helper('file');
		$this->load->helper('string');

		$upload_config = array(
			'upload_path' => FCPATH . 'local',
			'allowed_types' => 'csv',
			'max_size' => $this->data['max_size_bytes'],
			'encrypt_name' => TRUE,
		);

		$this->load->library('upload', $upload_config);

		// Default values supplied in form
		$defaults = array(
			'password' => $this->input->post('password'),
			'authlevel' => $this->input->post('authlevel'),
			'enabled' => $this->input->post('enabled'),
		);

		if ( ! $this->upload->do_upload()) {
			$error = $this->upload->display_errors('','');
			$this->data['notice'] = msgbox('error', $error);
			return FALSE;
		}

		$data = $this->upload->data();

		$file_path = $data['full_path'];
		$results = array();
		$handle = fopen($file_path, 'r');
		$line = 0;

		// Parse CSV file
		while (($row = fgetcsv($handle, filesize($file_path), ',')) !== FALSE) {

			if ($row[0] == 'username') {
				$line++;
				continue;
			}

			$user = array(
				'username' => trim($row[0]),
				'firstname' => trim($row[1]),
				'lastname' => trim($row[2]),
				'email' => trim($row[3]),
				'password' => trim($row[4]),
				'authlevel' => $defaults['authlevel'],
				'enabled' => $defaults['enabled'],
				'department_id' => NULL,
				'ext' => NULL,
				'displayname' => trim("{$row[1]} {$row[2]}"),
			);

			if (empty($user['password'])) {
				$user['password'] = $defaults['password'];
			}

			$status = $this->add_user($user);

			$results[] = array(
				'line' => $line,
				'status' => $status,
				'user' => $user,
			);

			$line++;

		}

		// Finish with CSV
		fclose($handle);
		@unlink($file_path);

		// Write results to temp file
		$data = json_encode($results);
		$res_filename = ".".random_string('alnum', 25);
		write_file(FCPATH . "local/{$res_filename}", $data);

		// Reference the file in the session for the next page to retrieve.
		$_SESSION['import_results'] = $res_filename;

		return redirect('users/import_results');
	}




	/**
	 * Add a user row from the imported CSV file
	 *
	 * @return  string		Description of the status of adding the given user
	 *
	 */
	private function _add_user($data = array())
	{
		if (empty($data['username'])) {
			return 'username_empty';
		}

		if (empty($data['password'])) {
			return 'password_empty';
		}

		if ($this->_userexists($data['username'])) {
			return 'username_exists';
		}

		$data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

		$res = $this->users_model->Add($data);

		if ($res) {
			return 'success';
		} else {
			return 'db_error';
		}
	}




	/**
	 * If there is a results file in the session, remove it, and unset the key.
	 *
	 */
	private function cleanup_import()
	{
		if (array_key_exists('import_results', $_SESSION)) {
			$file = $_SESSION['import_results'];
			@unlink(FCPATH . "local/{$file}");
			unset($_SESSION['import_results']);
		}
	}




	private function _userexists($username)
	{
		$sql = "SELECT user_id FROM users WHERE username='$username' LIMIT 1";
		$query = $this->db->query($sql);
		if ($query->num_rows() == 1) {
			return true;
		} else {
			return false;
		}
	}




}
