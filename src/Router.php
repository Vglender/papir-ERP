<?php

namespace Papir\Crm;

class Router
{
    private $routes = [
        '/' => '/pages/warehoses-np.html',
        '/papir/' => '/pages/warehoses-np.html',
        '/ttn-ukr' => '/pages/ttn-ukr.html',
        '/analitics' => '/pages/analitics.html',
        '/rightbar' => '/pages/rightbar.html',
        '/create-ttn' => '/pages/create-ttn.html',
        '/demand' => '/pages/demand.html',
        '/orders' => '/pages/orders.html',
        '/footer' => '/pages/footer.html',
        '/head' => '/pages/head.html',
        '/leftslider' => '/pages/leftslider.html',
        '/navbar' => '/pages/navbar.html',
        '/page-chat' => '/pages/page-chat.html',
        '/page-chat.html' => '/pages/page-chat.html',
        '/page-lock-screen' => '/pages/page-lock-screen.html',
        '/page-login' => '/pages/page-login.html',
        '/page-logout' => '/pages/page-logout.html',
        '/page-recoverpw' => '/pages/page-recoverpw.html',
        '/warehoses-np' => '/pages/warehoses-np.html',
        '/shiplist-ukr' => '/pages/shiplist-ukr.html',
		'/action' => '/pages/action.php',
		'/action-update-stock' => '/pages/action_update_stock.php',
		'/action-update-site' => '/pages/action_update_site.php',
		'/virtual' => '/pages/virtual.php',
		'/virtual-update-site' => '/pages/virtual-update-site.php',
		'/catalog' => '/pages/catalog.php',
		'/prices' => '/pages/prices.php',
		'/ukrsib_token_status' => '/modules/bank_ukrsib/tools/ukrsib_token_status.php',
		'/ukrsib_token_exchange' => '/modules/bank_ukrsib/tools/ukrsib_token_exchange.php',
		'/payments' => '/modules/payments_sync/tools/dashboard.php',
		'/docum/attr' => '/modules/moysklad/tools/dashboard.php',
		'/customerorder' => '/modules/customerorder/index.php',
		'/customerorder/edit' => '/modules/customerorder/edit.php',
		'/customerorder/save' => '/modules/customerorder/save.php',
		'/customerorder/item_add' => '/modules/customerorder/item_add.php',
		'/customerorder/item_delete' => '/modules/customerorder/item_delete.php',
		'/customerorder/item_update' => '/modules/customerorder/item_update.php',
		'/catalog-pricelist' => '/pages/catalog_pricelist.php',
		'/catalog-pricelist/' => '/pages/catalog_pricelist.php',
		'/customerorder/search_product' => '/modules/customerorder/search_product.php',
		'/customerorder/item_add_ajax' => '/modules/customerorder/item_add_ajax.php',
		'/customerorder/ajax_get_bank_accounts' => '/modules/customerorder/ajax_get_bank_accounts.php'

    ];

    public function handleRequest($requestUri)
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        $filePath = $this->resolveRoute($path);

        if ($filePath) {
            require __DIR__ . '/../' . $filePath;
        } else {
            http_response_code(404);
            require __DIR__ . '/../pages/page-404.html';
        }
    }

    // Функция для поиска маршрута с поддержкой .html и без него
	private function resolveRoute($path)
	{
		if ($path !== '/') {
			$path = rtrim($path, '/');
		}

		if (array_key_exists($path, $this->routes)) {
			return $this->routes[$path];
		}

		$pathWithoutHtml = preg_replace('/\.html$/', '', $path);
		if (array_key_exists($pathWithoutHtml, $this->routes)) {
			return $this->routes[$pathWithoutHtml];
		}

		return null;
	}

}
