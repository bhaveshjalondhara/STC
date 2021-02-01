<?php
namespace STC\StcPaymentGateway\Model\ResourceModel;


class MobilePayment extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
	
	public function __construct(
		\Magento\Framework\Model\ResourceModel\Db\Context $context
	)
	{
		parent::__construct($context);
	}
	
	protected function _construct()
	{
		$this->_init('stc_paymentgateway', 'id');
	}
	
}