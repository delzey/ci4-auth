<?php

namespace CI4\Auth\Controllers;

use App\Controllers\BaseController;
use CI4\Auth\Authorization\RoleModel;
use CI4\Auth\Config\Auth as AuthConfig;
use CodeIgniter\Session\Session;

class RoleController extends BaseController
{
    protected $auth;

    /**
     * @var AuthConfig
     */
    protected $config;
    protected $validation;

    /**
     * @var Session
     */
    protected $session;

    //-------------------------------------------------------------------------

    /**
     */
    public function __construct()
    {
        //
        // Most services in this controller require the session to be started
        //
        $this->session = service('session');
        $this->config = config('Auth');
        $this->auth = service('authorization');
        $this->validation       = \Config\Services::validation();
    }

    // -------------------------------------------------------------------------

    /**
     * Shows all role records.
     *
     * @return void
     */
    public function roles()
    {
        $roles = model(RoleModel::class);
        $allRoles = $roles->orderBy('name', 'asc')->findAll();

        $data = [
            'config' => $this->config,
            'roles' => $allRoles,
        ];

        foreach ($allRoles as $role) {
            $rolePermissions[$role->id][] = $roles->getPermissionsForRole($role->id);
        }
        $data['rolePermissions'] = $rolePermissions;

        if ($this->request->withMethod('post')) {
            //
            // A form was submitted. Let's see what it was...
            //
            if (array_key_exists('btn_delete', $this->request->getPost())) {
                //
                // [Delete]
                //
                $recId = $this->request->getPost('hidden_id');
                if (!$role = $roles->where('id', $recId)->first()) {
                    return redirect()->route('roles')->with('errors', lang('Auth.role.not_found', [$recId]));
                } else {
                    if (!$roles->deleteRole($recId)) {
                        $this->session->set('errors', $roles->errors());
                        return $this->_render($this->config->views['roles'], $data);
                    }
                    return redirect()->route('roles')->with('success', lang('Auth.role.delete_success', [$role->name]));
                }
            } else if (array_key_exists('btn_search', $this->request->getPost()) && array_key_exists('search', $this->request->getPost())) {
                //
                // [Search]
                //
                $search = $this->request->getPost('search');
                $where = '`name` LIKE "%' . $search . '%" OR `description` LIKE "%' . $search . '%"';
                $data['roles'] = $roles->where($where)->orderBy('name', 'asc')->findAll();
                $data['search'] = $search;
            }
        }

        return $this->_render($this->config->views['roles'], $data);
    }

    //-------------------------------------------------------------------------

    /**
     * Displays the user create page.
     */
    public function rolesCreate($id = null)
    {
        return $this->_render($this->config->views['rolesCreate'], ['config' => $this->config]);
    }

    //-------------------------------------------------------------------------

    /**
     * Attempt to create a new user.
     * To be be used by administrators. User will be activated automatically.
     */
    public function rolesCreateDo()
    {
        $roles = model(RoleModel::class);

        //
        // Validate input
        //
        $rules = $roles->validationRules;

        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        //
        // Save the role
        // Return to Create screen on fail
        //
        $id = $this->auth->createRole($this->request->getPost('name'), $this->request->getPost('description'));
        if (!$id) return redirect()->back()->withInput()->with('errors', $roles->errors());

        //
        // Success! Go back to user list
        //
        return redirect()->route('roles')->with('success', lang('Auth.role.create_success', [$this->request->getPost('name')]));
    }

    //-------------------------------------------------------------------------

    /**
     * Displays the user edit page.
     */
    public function rolesEdit($id = null)
    {
        $roles = model(RoleModel::class);

        if (!$role = $roles->where('id', $id)->first()) return redirect()->to('roles');

        $permissions = $this->auth->permissions();
        $rolePermissions = $roles->getPermissionsForRole($id);

        return $this->_render($this->config->views['rolesEdit'], [
            'config' => $this->config,
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    //-------------------------------------------------------------------------

    /**
     * Attempt to create a new role.
     */
    public function rolesEditDo($id = null)
    {
        $roles = model(RoleModel::class);

        //
        // Get the role to edit. If not found, return to roles list page.
        //
        if (!$role = $roles->where('id', $id)->first()) return redirect()->to('roles');

        //
        // Validate input
        //
        $fields['name']                 = $this->request->getPost('name');
        $fields['description']          = $this->request->getPost('description');

        $this->validation->setRules([
            'name'                      => ['label' => 'Name', 'rules'              => 'required|trim|max_length[255]|is_unique[auth_groups.name,id,'.$id.']'],
            'description'               => ['label' => 'Description', 'rules'       => 'permit_empty|trim|max_length[255]']
        ]);

        //
        // Save the group
        //
        if ($this->validation->run($fields) == FALSE) {
            return redirect()->back()->withInput()->with('errors', $this->validation->getErrors());
        } else {
            $this->auth->updateRole($id, $fields['name'], $fields['description']);
        }

        //
        // Save the permissions given to this role.
        // First, delete all permissions, then add each selected one.
        //
        $roles->removeAllPermissionsFromRole((int)$id);
        if (array_key_exists('sel_permissions', $this->request->getPost())) {
            foreach ($this->request->getPost('sel_permissions') as $perm) {
                $roles->addPermissionToRole($perm, $id);
            }
        }

        //
        // Success! Go back to roles list
        //
        return redirect()->back()->withInput()->with('success', lang('Auth.role.update_success', [$role->name]));
    }

    //-------------------------------------------------------------------------

    /**
     * Render View.
     *
     * @param string $view
     * @param array $data
     *
     * @return view
     */
    protected function _render(string $view, array $data = [])
    {
        //
        // In case you have a custom configuration that you want to pass to
        // your views (e.g. theme settings), it is added here.
        //
        // It is assumed that have declared and set the variable $myConfig in
        // your BaseController.
        //
        if (isset($this->myConfig)) $data['myConfig'] = $this->myConfig;

        return view($view, $data);
    }
}
