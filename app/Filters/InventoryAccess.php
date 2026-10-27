<?php
declare(strict_types=1);
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class InventoryAccess implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }
        if (!auth()->user()->can($arguments[0] ?? 'inventory.view')) {
            return service('response')->setStatusCode(403)->setBody(view('errors/access_denied'));
        }
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $response->setHeader('Cache-Control','no-store, private');
    }
}
