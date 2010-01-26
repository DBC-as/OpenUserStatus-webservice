<?php
/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
*/


require_once("OLS_class_lib/webServiceServer_class.php");
require_once("OLS_class_lib/oci_class.php");

class openUserStatus extends webServiceServer {

 /** \brief renewLoan - 
  *
  * Request:
  * - userId
  * - userPicode
  * - agencyId
  * - loanId

  * Response:
  * - renewLoanStatus
  * - - loanId
  * - - dateDue
  * - - dateOfExpectedReply
  * - - renewLoanError
  * - renewLoanError
  */
  function renewLoan($param) {
    //$oci = new Oci($this->config->get_value("agency_credentials","setup"));
    //$oci->set_charset("UTF8");
    //$oci->connect();

    $res->error->_value = "invalid_user";
    $one_item->loanId->_value = $some_id;
    $one_item->dateDue->_value = $some_date;
    $res->renewLoanStatus[]->_value = $one_item;

    $ret->renewLoanResponse->_value = $res;
    var_dump($param); die();
    return $ret;
  }

 /** \brief cancelOrder - 
  *
  * Request:
  * - userId
  * - userPicode
  * - agencyId
  * - cancelOrder
  * Response:
  * - cancelOrderStatus
  * - - orderId
  * - - orderCancelled
  * - - cancelOrderError
  * - cancelOrderError
  */
  function cancelOrder($param) {
    var_dump($param); die();
  }

 /** \brief getUserRequest - 
  *
  * Request:
  * - userId
  * - userPicode
  * - agencyId
  *
  * Response:
  * - userStatus
  * - - loanedItems
  * - - - loansCount
  * - - - loans
  * - - - - loanId
  * - - - - author
  * - - - - authorOfComponent
  * - - - - bibliographicItemId
  * - - - - bibliographicRecordId
  * - - - - componentId
  * - - - - edition
  * - - - - pagination
  * - - - - placeOfPublication
  * - - - - publicationDateOfComponent
  * - - - - publisher
  * - - - - seriesTitleNumber
  * - - - - titleOfComponent
  * - - - - bibliographicLevel
  * - - - - sponsoringBody
  * - - - - electronicDataFormatType
  * - - - - language
  * - - - - mediumType
  * - - - - publicationDate
  * - - - - title
  * - - - - dateDue
  * - - - - reminderLevel
  * - - - - loanRecallDate
  * - - orderedItems
  * - - - ordersCount
  * - - - order
  * - - - - orderId
  * - - - - author
  * - - - - authorOfComponent
  * - - - - bibliographicItemId
  * - - - - bibliographicRecordId
  * - - - - componentId
  * - - - - edition
  * - - - - pagination
  * - - - - placeOfPublication
  * - - - - publicationDateOfComponent
  * - - - - publisher
  * - - - - seriesTitleNumber
  * - - - - titleOfComponent
  * - - - - bibliographicLevel
  * - - - - sponsoringBody
  * - - - - electronicDataFormatType
  * - - - - language
  * - - - - mediumType
  * - - - - publicationDate
  * - - - - title
  * - - - - orderStatus
  * - - - - orderDate
  * - - - - orderType
  * - - - - dateAvailable
  * - - - - holdQueuePosition
  * - - - - needBeforeDate
  * - - - - orderExpiryDate
  * - - - - pickupDate
  * - - - - pickupExpiryDate
  * - - - - reminderLevel
  */
  function getUserStatus($param) {
    var_dump($param); die();
  }

}

/* 
 * MAIN 
 */

$ws=new openUserStatus('openuserstatus.ini');
$ws->handle_request();

?>
