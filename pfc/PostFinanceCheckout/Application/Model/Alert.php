<?php
/**
 * PostFinanceCheckout OXID
 *
 * This OXID module enables to process payments with PostFinanceCheckout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html/).
 *
 * @package Whitelabelshortcut\PostFinanceCheckout
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace Pfc\PostFinanceCheckout\Application\Model;
require_once(OX_BASE_PATH . 'modules/pfc/PostFinanceCheckout/autoload.php');


/**
 * Class Alert.
 */
class Alert
{
    const KEY_MANUAL_TASK = 'manual_task';

    protected static function getTableName()
    {
        return 'pfcPostFinanceCheckout_alert';
    }

    public static function setCount($key, $count) {
        $count = (int)$count;
        $key = \oxdb::getDb()->quote($key);
        $query = "UPDATE `pfcPostFinanceCheckout_alert` SET `pfccount`=$count WHERE `pfckey`=$key;";
        return \oxdb::getDb()->execute($query) === 1;
    }

    public static function modifyCount($key, $countModifier = 1) {
        $countModifier = (int)$countModifier;
        $key = \oxdb::getDb()->quote($key);
        $query = "UPDATE `pfcPostFinanceCheckout_alert` SET `PFCCOUNT`=`PFCCOUNT`+$countModifier WHERE `pfckey`=$key;";
        return \oxdb::getDb()->execute($query) === 1;
    }

    public static function loadAll() {
        $query = "SELECT `PFCKEY`, `PFCCOUNT`, `PFCFUNC`, `PFCTARGET` FROM `pfcPostFinanceCheckout_alert`";
        return \oxdb::getDb()->getAll($query);
    }
}