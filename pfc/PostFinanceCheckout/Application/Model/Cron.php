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

use Monolog\Logger;
use Pfc\PostFinanceCheckout\Core\PostFinanceCheckoutModule;

/**
 * Class Cron.
 * Cron model.
 */
class Cron
{
    const STATE_PENDING = 'pending';
    const STATE_PROCESS = 'process';
    const STATE_SUCCESS = 'success';
    const STATE_ERROR = 'error';
    const CONSTRAINT_PENDING = 0;
    const CONSTRAINT_PROCESSING = -1;
    const MAX_RUN_TIME_MINUTES = 10;
    const TIMEOUT_MINUTES = 5;

    protected static function getTableName()
    {
        return 'pfcPostFinanceCheckout_cron';
    }


    public static function setProcessing($oxid)
    {
        $table = self::getTableName();
        $constraint = self::CONSTRAINT_PROCESSING;
        $processing = self::STATE_PROCESS;
        $pending = self::STATE_PENDING;
        $time = new \DateTime();
        $time = $time->format('Y-m-d H:i:s');
        $oxid = \oxdb::getDb()->quote($oxid);
        $query = "UPDATE $table SET `PFCCONSTRAINT`='$constraint', `PFCSTATE`='$processing', `PFCSTARTED`='$time' WHERE `OXID`=$oxid AND `PFCSTATE`='$pending';";
        return !(\oxdb::getDb()->execute($query) === false);
    }

    public static function setComplete($oxid, $error = null)
    {
        $table = self::getTableName();
        $processing = self::STATE_PROCESS;
        $status = self::STATE_SUCCESS;
        if ($error) {
            $status = self::STATE_ERROR;
        }
        $time = new \DateTime();
        $time = $time->format('Y-m-d H:i:s');
        $error = \oxdb::getDb()->quote($error);
        $oxid = \oxdb::getDb()->quote($oxid);

        $query = "UPDATE $table SET `PFCCONSTRAINT`=OXID, `PFCSTATE`='$status', `PFCCOMPLETED`='$time', `PFCFAILUREREASON`=$error WHERE `OXID`=$oxid AND `PFCSTATE`='$processing';";
        return !(\oxdb::getDb()->execute($query) === false);
    }

    public static function cleanUpHangingCrons()
    {
        \oxdb::getDb()->startTransaction();
        $time = new \DateTime();
        $time->add(new \DateInterval('PT1M'));
        $processing = self::STATE_PROCESS;
        $error = self::STATE_ERROR;
        $timeoutMessage = 'Cron did not terminate correctly, timeout exceeded.';
        $table = self::getTableName();
        try {
            $timeout = new \DateTime();
            $timeout->sub(new \DateInterval('PT' . self::TIMEOUT_MINUTES . 'M'));
            $timeout = $timeout->format('Y-m-d H:i:s');
            $endTime = new \DateTime();
            $endTime = $endTime->format('Y-m-d H:i:s');
            $query = "UPDATE $table SET `PFCCONSTRAINT`=OXID, `PFCSTATE`='$error', `PFCCOMPLETED`='$endTime', `PFCFAILUREREASON`='$timeoutMessage' WHERE `PFCSTATE`='$processing' AND `PFCSTARTED`<'$timeout';";
            \oxdb::getDb()->execute($query);
            \oxdb::getDb()->commitTransaction();
        } catch (\Exception $e) {
            PostFinanceCheckoutModule::rollback();
            PostFinanceCheckoutModule::log(Logger::ERROR, 'Error clean up hanging cron: ' . $e->getMessage());
        }
    }

    public static function insertNewPendingCron()
    {
        \oxdb::getDb()->startTransaction();
        $pending = self::STATE_PENDING;
        $table = self::getTableName();
        try {
            $hasQuery = "SELECT `OXID` FROM $table WHERE `PFCSTATE`='$pending';";
            if (\oxdb::getDb()->getOne($hasQuery) !== false) {
                \oxdb::getDb()->commitTransaction();
                return false;
            }
            $oxid = PostFinanceCheckoutModule::getUtilsObject()->generateUId();
            $constraint = self::CONSTRAINT_PENDING;
            $time = new \DateTime();
            $time->add(new \DateInterval('PT1M'));
            $time = $time->format('Y-m-d H:i:s');
            $insertQuery = "INSERT INTO $table (`OXID`, `PFCCONSTRAINT`, `PFCSTATE`, `PFCSCHEDULED`) VALUES ('$oxid', '$constraint', '$pending', '$time');";
            $affected = \oxdb::getDb()->execute($insertQuery);
            \oxdb::getDb()->commitTransaction();
            return $affected === 1;
        } catch (\Exception $e) {
            PostFinanceCheckoutModule::rollback();
        }
        return false;
    }

    /**
     * Returns the current token or false if no pending job is scheduled to run
     *
     * @return string|false
     */
    public static function getCurrentPendingCron()
    {
        try {
            \oxdb::getDb()->startTransaction();
            $time = new \DateTime();
            $time->add(new \DateInterval('PT1M'));
            $pending = self::STATE_PENDING;
            $table = self::getTableName();
            $now = new \DateTime();
            $now = $now->format('Y-m-d H:i:s');
            $query = "SELECT `OXID` FROM $table WHERE `PFCSTATE`='$pending' AND `PFCSCHEDULED` < '$now';";
            $result = \oxdb::getDb()->getOne($query);
            \oxdb::getDb()->commitTransaction();
            return $result;
        } catch (\Exception $e) {
            PostFinanceCheckoutModule::log(Logger::ERROR, "CRON ERROR: {$e->getMessage()} - {$e->getTraceAsString()}.");
            PostFinanceCheckoutModule::rollback();
        }

        return false;
    }
}