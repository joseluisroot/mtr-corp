<?php
declare(strict_types=1);
namespace Config;

class Auth extends \CodeIgniter\Shield\Config\Auth
{
    public bool $allowRegistration = false;
}
