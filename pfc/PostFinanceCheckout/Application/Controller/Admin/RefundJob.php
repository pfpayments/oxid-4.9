<?php
/**
 * PostFinanceCheckout OXID
 *
 * This OXID module enables to process payments with PostFinanceCheckout (https://www.postfinance.ch/).
 *
 * @package Whitelabelshortcut\PostFinanceCheckout
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace Pfc\PostFinanceCheckout\Application\Controller\Admin;
require_once(OX_BASE_PATH . 'modules/pfc/PostFinanceCheckout/autoload.php');

use Monolog\Logger;
use Pfc\PostFinanceCheckout\Core\Service\RefundService;
use Pfc\PostFinanceCheckout\Core\PostFinanceCheckoutModule;


/**
 * Class RefundJob.
 */
class RefundJob extends \oxadminview
{
    /**
     * Controller template name.
     *
     * @var string
     */
    protected $_sThisTemplate = 'pfcPostFinanceCheckoutRefundJob.tpl';

    /**
     * @return mixed|string
     */
    public function render()
    {
        $mReturn = $this->_RefundJob_render_parent();

class_exists(\Pfc\PostFinanceCheckout\Application\Model\Transaction::class);        $transaction = oxNew(\Pfc\PostFinanceCheckout\Application\Model\Transaction::class);
        /* @var $transaction \Pfc\PostFinanceCheckout\Application\Model\Transaction */
        if ($transaction->loadByOrder($this->getEditObjectId())) {
            try {
                $transaction->pull();
                $this->_aViewData['lineItems'] = RefundService::instance()->getReducedItems($transaction);
                $this->_aViewData['oxTransactionId'] = $transaction->getId();
                return $mReturn;
            } catch (\Exception $e) {
                $error = PostFinanceCheckoutModule::instance()->translate("Unable to load transaction for order !id.", true, array('!id' => $this->getEditObjectId()));
                $error .= ' ' . $e->getMessage() . ' - ' . $e->getTraceAsString();
            }
        } else {
            $error = PostFinanceCheckoutModule::instance()->translate("Unable to load transaction for order !id.", true, array('!id' => $this->getEditObjectId()));
        }
        PostFinanceCheckoutModule::log(Logger::ERROR, $error);
        $this->_aViewData['pfc_error'] = $error;
        return 'pfcPostFinanceCheckoutError.tpl';
    }

    public function refund()
    {
class_exists(\Pfc\PostFinanceCheckout\Application\Model\Transaction::class);        $transaction = oxNew(\Pfc\PostFinanceCheckout\Application\Model\Transaction::class);
        /* @var $transaction \Pfc\PostFinanceCheckout\Application\Model\Transaction */

        try {
            if ($transaction->loadByOrder($this->getEditObjectId())) {
                $job = RefundService::instance()->create($transaction, false);
                $job->setFormReductions(PostFinanceCheckoutModule::instance()->getRequestParameter('item'));
                $job->setRestock(PostFinanceCheckoutModule::instance()->getRequestParameter('restock') !== null);
                $job->save();
                RefundService::instance()->send($job);
            } else {
                PostFinanceCheckoutModule::log(Logger::ERROR, "Unable to load transaction for order {$this->getEditObjectId()}.");
            }
        } catch (\Exception $e) {
            $refundId = "";
            if (isset($job)) {
                $refundId = " (" . $job->getId() . ")";
            }
            $message = "Unable to process refund $refundId for transaction {$transaction->getTransactionId()}. {$e->getMessage()} - {$e->getTraceAsString()}.";
            PostFinanceCheckoutModule::log(Logger::ERROR, $message);
            PostFinanceCheckoutModule::getUtilsView()->addErrorToDisplay($message);
        }

        \oxregistry::getUtils()->redirect(PostFinanceCheckoutModule::getUtilsUrl()->cleanUrlParams(PostFinanceCheckoutModule::getUtilsUrl()->appendUrl(PostFinanceCheckoutModule::getUtilsUrl()->getCurrentUrl(), array('cl' => 'pfc_postFinanceCheckout_Transaction', 'oxid' => $transaction->getOrderId(), 'cur' => $transaction->getOrderId())), '&'));
    }

    /**
     * Parent `render` call.
     * Method required for mocking.
     *
     * @codeCoverageIgnore
     *
     * @return mixed
     */
    protected function _RefundJob_render_parent()
    {
        return parent::render();
    }
}