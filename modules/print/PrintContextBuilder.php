<?php
/**
 * Dispatches context building to entity-specific builders.
 *
 * Usage:
 *   $context = PrintContextBuilder::build('order', $orderId, $orgId);
 *   $html    = $mustache->render($tpl['html_body'], $context);
 */
class PrintContextBuilder
{
    /**
     * @param string $entityType  'order' | ...
     * @param int    $entityId
     * @param int    $orgId       override organization (0 = use entity's own org)
     * @return array  Mustache context
     */
    public static function build($entityType, $entityId, $orgId = 0)
    {
        switch ($entityType) {
            case 'order':
                require_once __DIR__ . '/context/OrderContextBuilder.php';
                return OrderContextBuilder::build($entityId, $orgId);
            case 'demand':
                require_once __DIR__ . '/context/DemandContextBuilder.php';
                return DemandContextBuilder::build($entityId, $orgId);
            default:
                return array();
        }
    }
}
