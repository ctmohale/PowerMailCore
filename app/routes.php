<?php

declare(strict_types=1);

use App\Controllers\Admin\ApiKeyController;
use App\Controllers\Admin\ClientController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DomainController;
use App\Controllers\Admin\EmailAccountController;
use App\Controllers\Admin\EmailLogController;
use App\Controllers\Admin\EmailTemplateController;
use App\Controllers\Admin\InboxController;
use App\Controllers\Api\SendEmailController;
use App\Controllers\AuthController;
use App\Core\Router;

$router = new Router();

$router->get('/', [DashboardController::class, 'index'], auth: true);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], auth: true);

$router->get('/dashboard', [DashboardController::class, 'index'], auth: true);

$router->get('/clients', [ClientController::class, 'index'], auth: true);
$router->post('/clients', [ClientController::class, 'store'], auth: true);

$router->get('/domains', [DomainController::class, 'index'], auth: true);
$router->post('/domains', [DomainController::class, 'store'], auth: true);

$router->get('/email-accounts', [EmailAccountController::class, 'index'], auth: true);
$router->post('/email-accounts', [EmailAccountController::class, 'store'], auth: true);
$router->post('/email-accounts/{id}/inbox', [EmailAccountController::class, 'updateInbox'], auth: true);

$router->get('/email-templates', [EmailTemplateController::class, 'index'], auth: true);
$router->post('/email-templates', [EmailTemplateController::class, 'store'], auth: true);

$router->get('/api-keys', [ApiKeyController::class, 'index'], auth: true);
$router->post('/api-keys', [ApiKeyController::class, 'store'], auth: true);

$router->get('/email-logs', [EmailLogController::class, 'index'], auth: true);
$router->get('/email-logs/{id}', [EmailLogController::class, 'show'], auth: true);

$router->get('/inbox', [InboxController::class, 'index'], auth: true);
$router->post('/inbox/sync', [InboxController::class, 'sync'], auth: true);
$router->post('/inbox/sync-all', [InboxController::class, 'syncAll'], auth: true);
$router->get('/inbox/{id}', [InboxController::class, 'show'], auth: true);

$router->post('/api/send', [SendEmailController::class, 'send'], csrf: false);

return $router;
