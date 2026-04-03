<?php

class CustomerOrderController
{
    protected $service;

    public function __construct(CustomerOrderService $service)
    {
        $this->service = $service;
    }

    public function index($request = array())
    {
        $filters = array(
            'search' => isset($request['search']) ? trim($request['search']) : null,
            'id' => isset($request['id']) ? $request['id'] : null,
            'number' => isset($request['number']) ? $request['number'] : null,
            'status' => isset($request['status']) ? $request['status'] : null,
            'payment_status' => isset($request['payment_status']) ? $request['payment_status'] : null,
            'shipment_status' => isset($request['shipment_status']) ? $request['shipment_status'] : null,
            'manager_employee_id' => isset($request['manager_employee_id']) ? $request['manager_employee_id'] : null,
            'date_from' => isset($request['date_from']) ? $request['date_from'] : null,
            'date_to' => isset($request['date_to']) ? $request['date_to'] : null,
        );

        $sort = array(
            'field' => isset($request['sort_field']) ? $request['sort_field'] : 'id',
            'dir' => isset($request['sort_dir']) ? $request['sort_dir'] : 'DESC',
        );

        $page = isset($request['page']) ? (int)$request['page'] : 1;
        $limit = isset($request['limit']) ? (int)$request['limit'] : 50;

        return $this->service->getList($filters, $sort, $page, $limit);
    }
	public function searchProducts($request = array())
	{
		$query = isset($request['q']) ? $request['q'] : '';
		$limit = isset($request['limit']) ? (int)$request['limit'] : 15;

		return $this->service->searchProducts($query, $limit);
	}

    public function edit($id)
    {
        return $this->service->getOrderCard((int)$id);
    }

    public function create($request = array(), $employeeId = null)
    {
        return $this->service->createOrder($request, $employeeId);
    }

    public function save($id, $request = array(), $employeeId = null)
    {
        return $this->service->updateOrder((int)$id, $request, $employeeId);
    }

    public function addItem($orderId, $request = array(), $employeeId = null)
    {
        return $this->service->addItem((int)$orderId, $request, $employeeId);
    }

    public function updateItem($itemId, $request = array(), $employeeId = null)
    {
        return $this->service->updateItem((int)$itemId, $request, $employeeId);
    }

    public function deleteItem($itemId, $employeeId = null)
    {
        return $this->service->removeItem((int)$itemId, $employeeId);
    }

    public function saveAttributes($orderId, $attributes = array(), $employeeId = null)
    {
        return $this->service->saveAttributes((int)$orderId, $attributes, $employeeId);
    }

    public function changeStatus($orderId, $status, $employeeId = null)
    {
        return $this->service->changeStatus((int)$orderId, $status, $employeeId);
    }
}