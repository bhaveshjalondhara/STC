<?php

namespace STC\StcPaymentGateway\Block\Adminhtml;

use \Magento\Backend\Block\Template\Context;
use \Magento\Framework\App\RequestInterface;
use \STC\StcPaymentGateway\Model\MobilePaymentFactory;

class StcPayment extends \Magento\Backend\Block\Template
{

	protected $_request;
	protected $_mobilePaymentFactory;

	public function __construct(
		 Context $context,
		 RequestInterface $request,
		 MobilePaymentFactory $mobilePaymentFactory
		)
		{
			$this->_request = $request;
			$this->_mobilePaymentFactory = $mobilePaymentFactory;

			return parent::__construct($context);
		}


	public function getOrder()
	{
		$orderId = $this->_request->getParam('order_id');
		return $orderId;
	}

	public function getStcPaymentData()
	{	
		$order_id = $this->getOrder();
		$model = $this->_mobilePaymentFactory->create()
				->getCollection()
				->addFieldToFilter('order_id', $order_id);
		return $model;
	}
	
}