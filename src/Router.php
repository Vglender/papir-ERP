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
		'/action' => '/modules/action/index.php',
		'/action-update-stock' => '/modules/action/update_stock.php',
		'/action-update-site' => '/modules/action/update_site.php',
		'/virtual' => '/pages/virtual.php',
		'/virtual-update-site' => '/pages/virtual-update-site.php',
		'/catalog' => '/modules/catalog/index.php',
		'/manufacturers'                          => '/modules/catalog/manufacturers.php',
		'/category-mapping'                       => '/modules/catalog/category_mapping.php',
		'/catalog/api/get_manufacturers'          => '/modules/catalog/api/get_manufacturers.php',
		'/catalog/api/save_manufacturer'          => '/modules/catalog/api/save_manufacturer.php',
		'/catalog/api/save_manufacturer_record'   => '/modules/catalog/api/save_manufacturer_record.php',
		'/catalog/api/delete_manufacturer'        => '/modules/catalog/api/delete_manufacturer.php',
		'/catalog/api/get_site_categories'        => '/modules/catalog/api/get_site_categories.php',
		'/catalog/api/save_category_mapping'      => '/modules/catalog/api/save_category_mapping.php',
		'/catalog/api/save_product_category'      => '/modules/catalog/api/save_product_category.php',
		'/catalog/api/toggle_site_status'         => '/modules/catalog/api/toggle_site_status.php',
		'/catalog/api/toggle_bk_status'           => '/modules/catalog/api/toggle_bk_status.php',
		'/catalog/api/bulk_toggle_bk_status'      => '/modules/catalog/api/bulk_toggle_bk_status.php',
		'/categories'                             => '/modules/catalog/categories.php',
		'/categories/api/get'                     => '/modules/catalog/api/get_category.php',
		'/categories/api/save'                    => '/modules/catalog/api/save_category.php',
		'/categories/api/save_seo'                => '/modules/catalog/api/save_category_seo.php',
		'/categories/api/upload_image'            => '/modules/catalog/api/upload_image.php',
		'/categories/api/delete_image'            => '/modules/catalog/api/delete_image.php',
		'/ai/api/get_instruction'                 => '/modules/openai/api/get_instruction.php',
		'/ai/api/save_instruction'                => '/modules/openai/api/save_instruction.php',
		'/attributes'                             => '/modules/attributes/index.php',
		'/attributes/api/get'                     => '/modules/attributes/api/get_attributes.php',
		'/attributes/api/get_one'                 => '/modules/attributes/api/get_attribute.php',
		'/attributes/api/save'                    => '/modules/attributes/api/save_attribute.php',
		'/attributes/api/merge'                   => '/modules/attributes/api/merge_attribute.php',
		'/attributes/api/get_values'              => '/modules/attributes/api/get_values.php',
		'/attributes/api/save_value'              => '/modules/attributes/api/save_value.php',
		'/attributes/api/merge_value'             => '/modules/attributes/api/merge_value.php',
		'/attributes/api/save_group'              => '/modules/attributes/api/save_group.php',
		'/image-audit'                            => '/modules/image_audit/index.php',
		'/image-audit/api/action'                 => '/modules/image_audit/api/action.php',
		'/image-audit/api/status'                 => '/modules/image_audit/api/status.php',
		'/shared/api/upload_image'                => '/modules/shared/api/upload_image.php',
		'/shared/api/delete_image'                => '/modules/shared/api/delete_image.php',
		'/shared/api/replace_image'               => '/modules/shared/api/replace_image.php',
		'/shared/api/toggle_image_site'           => '/modules/shared/api/toggle_image_site.php',
		'/prices' => '/modules/prices/index.php',
		'/prices/api/get_product' => '/modules/prices/api/get_product.php',
		'/prices/api/recalculate' => '/modules/prices/api/recalculate_one.php',
		'/prices/api/save_global_settings' => '/modules/prices/api/save_global_settings.php',
		'/prices/api/recalculate_all'      => '/modules/prices/api/recalculate_all.php',
		'/prices/api/save_product_settings' => '/modules/prices/api/save_product_settings.php',
		'/prices/api/bulk_apply_settings' => '/modules/prices/api/bulk_apply_settings.php',
		'/prices/api/save_strategies'     => '/modules/prices/api/save_strategies.php',
		'/prices/api/sync_supplier'       => '/modules/prices/api/sync_supplier.php',
		'/prices/api/save_sheet_config'   => '/modules/prices/api/save_sheet_config.php',
		'/prices/api/match_item'          => '/modules/prices/api/match_item.php',
		'/prices/api/search_catalog'      => '/modules/prices/api/search_catalog.php',
		'/prices/api/toggle_pricelist'    => '/modules/prices/api/toggle_pricelist.php',
		'/prices/api/create_supplier'     => '/modules/prices/api/create_supplier.php',
		'/prices/api/create_pricelist'    => '/modules/prices/api/create_pricelist.php',
		'/prices/api/delete_pricelist'    => '/modules/prices/api/delete_pricelist.php',
		'/prices/api/delete_supplier'     => '/modules/prices/api/delete_supplier.php',
		'/prices/api/update_stock'        => '/modules/prices/api/update_stock.php',
		'/prices/api/save_pricelist_item' => '/modules/prices/api/save_pricelist_item.php',
		'/prices/api/toggle_manual_edit'  => '/modules/prices/api/toggle_manual_edit.php',
		'/prices/api/add_pricelist_item'  => '/modules/prices/api/add_pricelist_item.php',
		'/prices/api/delete_pricelist_item' => '/modules/prices/api/delete_pricelist_item.php',
		'/prices/api/rename_pricelist'      => '/modules/prices/api/rename_pricelist.php',
		'/prices/api/push_prices'           => '/modules/prices/api/push_prices.php',
		'/prices/suppliers'               => '/modules/prices/suppliers.php',
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
		'/customerorder/ajax_get_bank_accounts' => '/modules/customerorder/ajax_get_bank_accounts.php',
		'/catalog/api/add_to_site'              => '/modules/catalog/api/add_to_site.php',
		'/catalog/api/set_main_image'           => '/modules/catalog/api/set_main_image.php',
		'/catalog/api/delete_product'           => '/modules/catalog/api/delete_product.php'

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
