<?php
declare(strict_types=1);
namespace Config;

class Auth extends \CodeIgniter\Shield\Config\Auth
{
    public bool $allowRegistration = false;
    public function __construct()
    {
        parent::__construct();
        $this->views['login'] = 'auth/login';
        $this->redirects['login'] = '/inventario';
        $this->redirects['logout'] = '/login';
    }
}
