<?php
namespace STC\StcPaymentGateway\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderObserver implements ObserverInterface
{
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
	    $order = $observer->getEvent()->getOrder();
	    // also if you want to check condition here like only offline payment method than order status update etc....
	    $order->setState("pending")->setStatus("pending");
	    $order->save(); 
	}
}