<?php
/*
 * Copyright (c) 2013 - 2024 MasterCard International Incorporated
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification, are 
 * permitted provided that the following conditions are met:
 * 
 * Redistributions of source code must retain the above copyright notice, this list of 
 * conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of 
 * conditions and the following disclaimer in the documentation and/or other materials 
 * provided with the distribution.
 * Neither the name of the MasterCard International Incorporated nor the names of its 
 * contributors may be used to endorse or promote products derived from this software 
 * without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT 
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, 
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER 
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING 
 * IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF 
 * SUCH DAMAGE.
 */


class Simplify_Customer extends Simplify_Object {
    /**
     * Creates an Simplify_Customer object
     * @param     array $hash a map of parameters; valid keys are:<dl style="padding-left:10px;">
     *     <dt><tt>card.addressCity</tt></dt>    <dd>City of the cardholder. <strong>required </strong></dd>
     *     <dt><tt>card.addressCountry</tt></dt>    <dd>Country code (ISO-3166-1-alpha-2 code) of residence of the cardholder. <strong>required </strong></dd>
     *     <dt><tt>card.addressLine1</tt></dt>    <dd>Address of the cardholder <strong>required </strong></dd>
     *     <dt><tt>card.addressLine2</tt></dt>    <dd>Address of the cardholder if needed. <strong>required </strong></dd>
     *     <dt><tt>card.addressState</tt></dt>    <dd>State of residence of the cardholder. State abbreviations should be used. <strong>required </strong></dd>
     *     <dt><tt>card.addressZip</tt></dt>    <dd>Postal code of the cardholder. The postal code size is between 5 and 9 in length and only contain numbers or letters. <strong>required </strong></dd>
     *     <dt><tt>card.cvc</tt></dt>    <dd>CVC security code of the card. This is the code on the back of the card. Example: 123 <strong>required </strong></dd>
     *     <dt><tt>card.expMonth</tt></dt>    <dd>Expiration month of the card. Format is MM. Example: January = 01 <strong>required </strong></dd>
     *     <dt><tt>card.expYear</tt></dt>    <dd>Expiration year of the card. Format is YY. Example: 2013 = 13 <strong>required </strong></dd>
     *     <dt><tt>card.id</tt></dt>    <dd>ID of card. Unused during customer create. </dd>
     *     <dt><tt>card.name</tt></dt>    <dd>Name as appears on the card. <strong>required </strong></dd>
     *     <dt><tt>card.number</tt></dt>    <dd>Card number as it appears on the card. [max length: 19, min length: 13] </dd>
     *     <dt><tt>email</tt></dt>    <dd>Email address of the customer <strong>required </strong></dd>
     *     <dt><tt>name</tt></dt>    <dd>Customer name [max length: 50, min length: 2] <strong>required </strong></dd>
     *     <dt><tt>reference</tt></dt>    <dd>Reference field for external applications use. </dd>
     *     <dt><tt>subscriptions.amount</tt></dt>    <dd>Amount of payment in the smallest unit of your currency. Example: 100 = $1.00 </dd>
     *     <dt><tt>subscriptions.billingCycle</tt></dt>    <dd>How the plan is billed to the customer. Values must be AUTO (indefinitely until the customer cancels) or FIXED (a fixed number of billing cycles). [default: AUTO] </dd>
     *     <dt><tt>subscriptions.billingCycleLimit</tt></dt>    <dd>The number of fixed billing cycles for a plan. Only used if the billingCycle parameter is set to FIXED. Example: 4 </dd>
     *     <dt><tt>subscriptions.coupon</tt></dt>    <dd>Coupon associated with the subscription for the customer. </dd>
     *     <dt><tt>subscriptions.currency</tt></dt>    <dd>Currency code (ISO-4217). Must match the currency associated with your account. </dd>
     *     <dt><tt>subscriptions.currentPeriodEnd</tt></dt>    <dd>End date of subscription's current period </dd>
     *     <dt><tt>subscriptions.currentPeriodStart</tt></dt>    <dd>Start date of subscription's current period </dd>
     *     <dt><tt>subscriptions.customer</tt></dt>    <dd>The customer ID to create the subscription for. Do not supply this when creating a customer. </dd>
     *     <dt><tt>subscriptions.frequency</tt></dt>    <dd>Frequency of payment for the plan. Used in conjunction with frequencyPeriod. Valid values are "DAILY", "WEEKLY", "MONTHLY" and "YEARLY". </dd>
     *     <dt><tt>subscriptions.frequencyPeriod</tt></dt>    <dd>Period of frequency of payment for the plan. Example: if the frequency is weekly, and periodFrequency is 2, then the subscription is billed bi-weekly. </dd>
     *     <dt><tt>subscriptions.name</tt></dt>    <dd>Name describing subscription [max length: 50] </dd>
     *     <dt><tt>subscriptions.plan</tt></dt>    <dd>The plan ID that the subscription should be created from. </dd>
     *     <dt><tt>subscriptions.quantity</tt></dt>    <dd>Quantity of the plan for the subscription. [min value: 1] </dd>
     *     <dt><tt>subscriptions.renewalReminderLeadDays</tt></dt>    <dd>If set, how many days before the next billing cycle that a renewal reminder is sent to the customer. If null, then no emails are sent. Minimum value is 7 if set. </dd>
     *     <dt><tt>subscriptions.source</tt></dt>    <dd>Source of where subscription was created </dd>
     *     <dt><tt>token</tt></dt>    <dd>If specified, card associated with card token will be used </dd></dl>
     * @param     $authentication -  information used for the API call.  If no value is passed the global keys Simplify::public_key and Simplify::private_key are used.  <i>For backwards compatibility the public and private keys may be passed instead of the authentication object.<i/>
     * @return    Customer a Customer object.
     */
    static public function createCustomer($hash, $authentication = null) {

        $args = func_get_args();
        $authentication = Simplify_PaymentsApi::buildAuthenticationObject($authentication, $args, 2);

        $instance = new Simplify_Customer();
        $instance->setAll($hash);

        $object = Simplify_PaymentsApi::createObject($instance, $authentication);
        return $object;
    }




       /**
        * Deletes an Simplify_Customer object.
        *
        * @param     $authentication -  information used for the API call.  If no value is passed the global keys Simplify::public_key and Simplify::private_key are used.  <i>For backwards compatibility the public and private keys may be passed instead of the authentication object.</i>
        */
        public function deleteCustomer($authentication = null) {

            $args = func_get_args();
            $authentication = Simplify_PaymentsApi::buildAuthenticationObject($authentication, $args, 1);

            $obj = Simplify_PaymentsApi::deleteObject($this, $authentication);
            $this->properties = null;
            return true;
        }


       /**
        * Retrieve Simplify_Customer objects.
        * @param     array criteria a map of parameters; valid keys are:<dl style="padding-left:10px;">
        *     <dt><tt>filter</tt></dt>    <dd><table class="filter_list"><tr><td>filter.id</td><td>Filter by the customer Id</td></tr><tr><td>filter.text</td><td>Can use this to filter by the name, email or reference for the customer</td></tr><tr><td>filter.dateCreatedMin<sup>*</sup></td><td>Filter by the minimum created date you are searching for - Date in UTC millis</td></tr><tr><td>filter.dateCreatedMax<sup>*</sup></td><td>Filter by the maximum created date you are searching for - Date in UTC millis</td></tr></table><br><sup>*</sup>Use dateCreatedMin with dateCreatedMax in the same filter if you want to search between two created dates  </dd>
        *     <dt><tt>max</tt></dt>    <dd>Allows up to a max of 50 list items to return. [min value: 0, max value: 50, default: 20]  </dd>
        *     <dt><tt>offset</tt></dt>    <dd>Used in paging of the list.  This is the start offset of the page. [min value: 0, default: 0]  </dd>
        *     <dt><tt>sorting</tt></dt>    <dd>Allows for ascending or descending sorting of the list.  The value maps properties to the sort direction (either <tt>asc</tt> for ascending or <tt>desc</tt> for descending).  Sortable properties are: <tt> dateCreated</tt><tt> id</tt><tt> name</tt><tt> email</tt><tt> reference</tt>.</dd></dl>
        * @param     $authentication -  information used for the API call.  If no value is passed the global keys Simplify::public_key and Simplify::private_key are used.  <i>For backwards compatibility the public and private keys may be passed instead of the authentication object.</i>
        * @return    ResourceList a ResourceList object that holds the list of Customer objects and the total
        *            number of Customer objects available for the given criteria.
        * @see       ResourceList
        */
        static public function listCustomer($criteria = null, $authentication = null) {

            $args = func_get_args();
            $authentication = Simplify_PaymentsApi::buildAuthenticationObject($authentication, $args, 2);

            $val = new Simplify_Customer();
            $list = Simplify_PaymentsApi::listObject($val, $criteria, $authentication);

            return $list;
        }


        /**
         * Retrieve a Simplify_Customer object from the API
         *
         * @param     string id  the id of the Customer object to retrieve
         * @param     $authentication -  information used for the API call.  If no value is passed the global keys Simplify::public_key and Simplify::private_key are used.  <i>For backwards compatibility the public and private keys may be passed instead of the authentication object.</i>
         * @return    Customer a Customer object
         */
        static public function findCustomer($id, $authentication = null) {

            $args = func_get_args();
            $authentication = Simplify_PaymentsApi::buildAuthenticationObject($authentication, $args, 2);

            $val = new Simplify_Customer();
            $val->id = $id;

            $obj = Simplify_PaymentsApi::findObject($val, $authentication);

            return $obj;
        }


        /**
         * Updates an Simplify_Customer object.
         *
         * The properties that can be updated:
         * <dl style="padding-left:10px;">
         *     <dt><tt>card.addressCity</tt></dt>    <dd>City of the cardholder. <strong>required </strong></dd>
         *     <dt><tt>card.addressCountry</tt></dt>    <dd>Country code (ISO-3166-1-alpha-2 code) of residence of the cardholder. <strong>required </strong></dd>
         *     <dt><tt>card.addressLine1</tt></dt>    <dd>Address of the cardholder. <strong>required </strong></dd>
         *     <dt><tt>card.addressLine2</tt></dt>    <dd>Address of the cardholder if needed. <strong>required </strong></dd>
         *     <dt><tt>card.addressState</tt></dt>    <dd>State of residence of the cardholder. State abbreviations should be used. <strong>required </strong></dd>
         *     <dt><tt>card.addressZip</tt></dt>    <dd>Postal code of the cardholder. The postal code size is between 5 and 9 in length and only contain numbers or letters. <strong>required </strong></dd>
         *     <dt><tt>card.cvc</tt></dt>    <dd>CVC security code of the card. This is the code on the back of the card. Example: 123 <strong>required </strong></dd>
         *     <dt><tt>card.expMonth</tt></dt>    <dd>Expiration month of the card. Format is MM.  Example: January = 01 <strong>required </strong></dd>
         *     <dt><tt>card.expYear</tt></dt>    <dd>Expiration year of the card. Format is YY. Example: 2013 = 13 <strong>required </strong></dd>
         *     <dt><tt>card.id</tt></dt>    <dd>ID of card. If present, card details for the customer will not be updated. If not present, the customer will be updated with the supplied card details. </dd>
         *     <dt><tt>card.name</tt></dt>    <dd>Name as appears on the card. <strong>required </strong></dd>
         *     <dt><tt>card.number</tt></dt>    <dd>Card number as it appears on the card. [max length: 19, min length: 13] </dd>
         *     <dt><tt>email</tt></dt>    <dd>Email address of the customer <strong>required </strong></dd>
         *     <dt><tt>name</tt></dt>    <dd>Customer name [max length: 50, min length: 2] <strong>required </strong></dd>
         *     <dt><tt>reference</tt></dt>    <dd>Reference field for external applications use. </dd>
         *     <dt><tt>token</tt></dt>    <dd>If specified, card associated with card token will be added to the customer </dd></dl>
         * @param     $authentication -  information used for the API call.  If no value is passed the global keys Simplify::public_key and Simplify::private_key are used.  <i>For backwards compatibility the public and private keys may be passed instead of the authentication object.</i>
         * @return    Customer a Customer object.
         */
        public function updateCustomer($authentication = null)  {

            $args = func_get_args();
            $authentication = Simplify_PaymentsApi::buildAuthenticationObject($authentication, $args, 1);

            $object = Simplify_PaymentsApi::updateObject($this, $authentication);
            return $object;
        }

    /**
     * @ignore
     */
    public function getClazz() {
        return "Customer";
    }
}