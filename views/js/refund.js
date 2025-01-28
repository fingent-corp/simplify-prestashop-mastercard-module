/*
 * Copyright (c) 2023 Mastercard
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

document.addEventListener('DOMContentLoaded', function () {

    const container = document.querySelector('#view_order_payments_block'); // Replace with the actual container ID

    if (container && typeof refundData !== 'undefined') {
        // Create a div element with a specific class
        const divElement = document.createElement('div');
        divElement.className = 'card mt-2'; 
        const divSubelement = document.createElement('div');
        divSubelement.className = 'card-header'; 
        const titleElement = document.createElement('h3');
        titleElement.className = 'card-header-title';
        const refundDataArray = JSON.parse(refundData);
        titleElement.textContent = `Refund Details (${refundDataArray.length})`;
        
        divSubelement.appendChild(titleElement);
        const divbodyelement = document.createElement('div');
        divbodyelement.className = 'card-body'; 

        // Create a table element to display the refund details
        const tableElement = document.createElement('table');
        tableElement.className = 'table'; 

        // Create table headers
        const tableHeaders = ['Refund ID', 'Refund Description', 'Amount', 'Date','Comment'];
        const headerRow = document.createElement('tr');
        tableHeaders.forEach(headerText => {
            const headerCell = document.createElement('th');
            const headerDiv = document.createElement('div');
            headerDiv.textContent = headerText;
            headerCell.appendChild(headerDiv);
            headerRow.appendChild(headerCell);
        });

        // Append the header row to the table
        const thead = document.createElement('thead');
        thead.appendChild(headerRow);
        tableElement.appendChild(thead);

        let dollarSign = currency.sign;
        // Loop through the refund data and create table rows and cells for each detail
        refundDataArray.sort((a, b) => new Date(b.date_created) - new Date(a.date_created));
        refundDataArray.forEach(function (data) {
            const row = tableElement.insertRow();
            const idCell = row.insertCell();
            idCell.textContent = data.refund_id;
            const descriptionCell = row.insertCell();
            descriptionCell.textContent = data.refund_description;
            const amountCell = row.insertCell();
            amountCell.textContent = dollarSign + data.amount;
            const dateCell = row.insertCell();
            dateCell.textContent = data.date_created;
            const commentCell = row.insertCell();
            commentCell.textContent = data.comment;
        });

        divbodyelement.appendChild(tableElement);
        divElement.appendChild(divSubelement);
        divElement.appendChild(divbodyelement);

        container.insertAdjacentElement('beforebegin', divElement);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    
    const container = document.querySelector('#view_order_payments_block'); 

    if (container && typeof captureData !== 'undefined') {
        // Create a div element with a specific class
        const divElement = document.createElement('div');
        divElement.className = 'card mt-2'; 
        const divSubelement = document.createElement('div');
        divSubelement.className = 'card-header'; 
        const titleElement = document.createElement('h3');
        titleElement.className = 'card-header-title';
        const captureDataArray = JSON.parse(captureData);
        titleElement.textContent = `Capture Details (${captureDataArray.length})`;
        divSubelement.appendChild(titleElement);
        const divbodyelement = document.createElement('div');
        divbodyelement.className = 'card-body'; 
        // Create a table element to display the capture details
        const tableElement = document.createElement('table');
        tableElement.className = 'table'; // Replace with your desired class name

        let dollarSign = currency.sign;
        // Create table headers
        const tableHeaders = ['Capture ID', 'Amount','Date','Comment'];
        const headerRow = document.createElement('tr');
        tableHeaders.forEach(headerText => {
            const headerCell = document.createElement('th');
            const headerDiv = document.createElement('div');
            headerDiv.textContent = headerText;
            headerCell.appendChild(headerDiv);
            headerRow.appendChild(headerCell);
        });

        // Append the header row to the table
        const thead = document.createElement('thead');
        thead.appendChild(headerRow);
        tableElement.appendChild(thead);
        captureDataArray.sort((a, b) => new Date(b.date_created) - new Date(a.date_created));
        // Loop through the capture data and create table rows and cells for each detail
        captureDataArray.forEach(function (data) {
            const row = tableElement.insertRow();
            const idCell = row.insertCell();
            idCell.textContent = data.payment_transcation_id;
            const amountCell = row.insertCell();
            amountCell.textContent = dollarSign + data.amount;
            const dateCell = row.insertCell();
            dateCell.textContent = data.transcation_date;
            const commentCell = row.insertCell();
            commentCell.textContent = data.comment;
        });

        divbodyelement.appendChild(tableElement);
        divElement.appendChild(divSubelement);
        divElement.appendChild(divbodyelement);
        container.insertAdjacentElement('beforebegin', divElement);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    let cancelButton = document.querySelector('#refundpartial');
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            let orderId = document.querySelector('form[name="cancel_product"]').getAttribute('data-order-id');
            let orderTotalNumber = getOrderTotal();
            let balanceamount = calculateBalanceAmount(orderTotalNumber);

            const container = document.querySelector('.product-row');
            toggleRefundOption(container, orderId, orderTotalNumber, balanceamount);
        });
    }
});

function getOrderTotal() {
    let orderTotalElement = document.getElementById('orderTotal');
    if (orderTotalElement) {
        let orderTotalValue = orderTotalElement.textContent;
        return parseFloat(orderTotalValue.replace('$', ''));
    }
    return 0;
}

function calculateBalanceAmount(orderTotalNumber) {
    if (typeof refundmaxamount !== 'undefined') {
        return (orderTotalNumber - refundmaxamount).toFixed(2);
    } else {
        return orderTotalNumber.toFixed(2);
    }
}

function toggleRefundOption(container, orderId, orderTotalNumber, balanceamount) {
    const fullRefundElement = document.querySelector('.full-refund-card');
    let partialRefundElement = document.querySelector('.partial-refund-card');

    if (container !== null) {
        if (fullRefundElement) {
            fullRefundElement.remove();
        }

        if (partialRefundElement) {
            partialRefundElement.style.display = partialRefundElement.style.display === 'none' ? 'block' : 'none';
        } else {
            createPartialRefundOption(container, orderId, orderTotalNumber, balanceamount);
        }
    }
}

function createPartialRefundOption(container, orderId, orderTotalNumber, balanceamount) {
    const dollarSign = currency.sign;
    const divElement = document.createElement('div');
    divElement.className = 'card partial-refund-card';

    const titleElement = createPartialTitleElement();
    const amountInput = createAmountInput();
    const maxAmountText = createMaxAmountText(balanceamount, dollarSign);
    const reasonInputWrapper = createPartialReasonInputWrapper();
    const submitButton = createPartialSubmitButton(orderId, amountInput, reasonInputWrapper, orderTotalNumber, balanceamount);
    const cancelButton = createPartialCancelButton(divElement, amountInput, reasonInputWrapper);

    divElement.appendChild(titleElement);
    divElement.appendChild(amountInput);
    divElement.appendChild(maxAmountText);
    divElement.appendChild(reasonInputWrapper);
    divElement.appendChild(submitButton);
    divElement.appendChild(cancelButton);

    container.insertAdjacentElement('beforebegin', divElement);
}

function createPartialTitleElement() {
    const titleElement = document.createElement('p');
    titleElement.className = 'card-header';
    titleElement.textContent = 'Partial Refund';
    return titleElement;
}

function createAmountInput() {
    const amountInput = document.createElement('input');
    amountInput.type = 'number';
    amountInput.placeholder = 'Amount';
    amountInput.className = 'form-control amount-input';
    return amountInput;
}

function createMaxAmountText(balanceamount, dollarSign) {
    const maxAmountText = document.createElement('p');
    maxAmountText.className = 'card-body balance-text';
    maxAmountText.textContent = 'Max Amount: ' + dollarSign + balanceamount;
    return maxAmountText;
}

function createPartialReasonInputWrapper() {
    const innerdiv = document.createElement('div');
    innerdiv.className = 'reason-input-wrapper';
    
    const reasonInput = document.createElement('textarea');
    reasonInput.placeholder = 'Reason';
    reasonInput.className = 'form-control reason-input';
    reasonInput.rows = '4';
    reasonInput.cols = '50';
    reasonInput.maxLength = 100;

    const maxText = document.createElement('span');
    maxText.className = 'card-body balance-text remaining-word-count';
    
    reasonInput.addEventListener('input', function() {
        if (this.value.length >= 100) {
            alert('You have Reached the Maximum Word Length of 100 Characters.');
        } else {
            this.setCustomValidity('');
        }
    });

    innerdiv.appendChild(reasonInput);
    innerdiv.appendChild(maxText);
    
    return innerdiv;
}

function createPartialSubmitButton(orderId, amountInput, reasonInputWrapper, orderTotalNumber, balanceamount) {
    const submitButton = document.createElement('button');
    submitButton.textContent = 'Submit';
    submitButton.className = 'btn btn-primary submit';
    
    submitButton.addEventListener('click', function() {
        handleRefundSubmit(amountInput, reasonInputWrapper, orderId, orderTotalNumber, balanceamount);
    });
    
    return submitButton;
}

function createPartialCancelButton(divElement, amountInput, reasonInputWrapper) {
    const cancelButton = document.createElement('button');
    cancelButton.textContent = 'Cancel';
    cancelButton.className = 'btn btn-secondary';
    
    cancelButton.addEventListener('click', function() {
        cancelRefundPartial(amountInput, reasonInputWrapper, divElement);
    });

    return cancelButton;
}

function handleRefundSubmit(amountInput, reasonInputWrapper, orderId, orderTotalNumber, balanceamount) {
    showLoader();
    const amount = amountInput.value;
    const reason = reasonInputWrapper.querySelector('textarea').value;

    if (isNaN(parseFloat(amount)) || parseFloat(amount) <= 0 || parseFloat(amount) > parseFloat(balanceamount)) {
        alert('Please enter a valid amount between 0 and ' + balanceamount);
        hideLoader();
        return;
    }

    $.ajax({
        type: 'POST',
        cache: false,
        dataType: 'json',
        url: adminajax_link, 
        data: {
            ajax: true,
            action: 'partialRefund',
            RefundAmount: amount,
            Refundreason: reason,
            OrderId: orderId,
            ProductAmount: orderTotalNumber,
        },
        success: function(data, response) {
            handlePartialRefundResponse(data, response, amountInput, reasonInputWrapper);
        },
        error: function(data, response) {
            handlePartialRefundError(data, response, amountInput, reasonInputWrapper);
        }
    });
}

function handlePartialRefundResponse(data, response, amountInput, reasonInputWrapper) {
    const partialresponse = JSON.parse(data);
    const divElement = amountInput.closest('.partial-refund-card');

    if (partialresponse.status === 'success') {
        $('#ajax_confirmation').html('A Partial Refund was Successfully Created.').show();
        amountInput.value = '';
        reasonInputWrapper.querySelector('textarea').value = '';
        divElement.style.display = 'none';
        hideLoader();
        setTimeout(function(){
            window.location.reload();
        }, 2000);
    } else if (partialresponse.status === 'failed') {
        showErrorPartial('The Partial Refund Failed.', amountInput, reasonInputWrapper, divElement);
    }
}

function handlePartialRefundError(data, response, amountInput, reasonInputWrapper) {
    console.log(data, response);
    showErrorPartial('Error : Partial Refund Failed.', amountInput, reasonInputWrapper);
}

function showErrorPartial(message, amountInput, reasonInputWrapper, divElement = null) {
    $('#ajax_confirmation').html(message).show();
    $('#ajax_confirmation').css({'color': '#363a41','background-color': '#fbc6c3','border': '1px solid #f44336'});
    $('#ajax_confirmation').removeClass('alert-success').addClass('alert-danger');
    amountInput.value = '';
    reasonInputWrapper.querySelector('textarea').value = '';
    if (divElement) divElement.style.display = 'none';
    hideLoader();
}

function cancelRefundPartial(amountInput, reasonInputWrapper, divElement) {
    amountInput.value = '';
    reasonInputWrapper.querySelector('textarea').value = '';
    divElement.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    let refundButton = document.querySelector('#fullrefund');
    if (refundButton) {
        refundButton.addEventListener('click', function() {
            const orderId          = getOrderId();
            const orderTotalNumber = getOrderTotalNumber();
            const container        = document.querySelector('.product-row');
            
            if (container) {
                removePartialRefund();
                toggleFullRefund(container, orderId, orderTotalNumber);
            }
        });
    }
});

function getOrderId() {
    return document.querySelector('form[name="cancel_product"]').getAttribute('data-order-id');
}

function getOrderTotalNumber() {
    const orderTotalElement = document.getElementById('orderTotal');
    return orderTotalElement ? parseFloat(orderTotalElement.textContent.replace('$', '')) : 0;
}

function removePartialRefund() {
    const partialRefundElement = document.querySelector('.partial-refund-card');
    if (partialRefundElement) partialRefundElement.remove();
}

function toggleFullRefund(container, orderId, orderTotalNumber) {
    const fullRefundElement = document.querySelector('.full-refund-card');
    
    if (fullRefundElement) {
        fullRefundElement.style.display = fullRefundElement.style.display === 'none' ? 'block' : 'none';
    } else {
        createFullRefundForm(container, orderId, orderTotalNumber);
    }
}

function createFullRefundForm(container, orderId, orderTotalNumber) {
    const divElement     = document.createElement('div');
    divElement.className = 'card full-refund-card';

    const titleElement       = createTitleElement();
    const amountElement      = createAmountElement(orderTotalNumber);
    const reasonInputWrapper = createReasonInputWrapper();
    const submitButton       = createSubmitButton(orderId, orderTotalNumber, reasonInputWrapper);
    const cancelButton       = createCancelButton(reasonInputWrapper);

    divElement.appendChild(titleElement);
    divElement.appendChild(amountElement);
    divElement.appendChild(reasonInputWrapper);
    divElement.appendChild(submitButton);
    divElement.appendChild(cancelButton);

    container.insertAdjacentElement('beforebegin', divElement);
}

function createTitleElement() {
    const titleElement       = document.createElement('p');
    titleElement.className   = 'card-header';
    titleElement.textContent = 'Full Refund';
    return titleElement;
}

function createAmountElement(orderTotalNumber) {
    const amountElement       = document.createElement('p');
    amountElement.className   = 'card-body amount-display';
    amountElement.textContent = 'Refund Amount: ' + currency.sign + orderTotalNumber.toFixed(2);
    return amountElement;
}

function createReasonInputWrapper() {
    const innerdiv     = document.createElement('div');
    innerdiv.className = 'reason-input-wrapper';
    
    const reasonInput       = document.createElement('textarea');
    reasonInput.placeholder = 'Reason';
    reasonInput.className   = 'form-control reason-input';
    reasonInput.rows        = '4';
    reasonInput.cols        = '50';
    reasonInput.maxLength   = 100;

    const maxText     = document.createElement('span');
    maxText.className = 'card-body balance-text remaining-word-count';

    reasonInput.addEventListener('input', function() {
        if (this.value.length >= 100) {
            alert('You have reached the maximum word length of 100 characters.');
        } else {
            this.setCustomValidity('');
        }
    });

    innerdiv.appendChild(reasonInput);
    innerdiv.appendChild(maxText);

    updateRemainingWordCount(reasonInput, maxText);

    return innerdiv;
}

function updateRemainingWordCount(reasonInput, maxText) {
    const remainingWordLengthDisplay = maxText;
    
    reasonInput.addEventListener('input', function() {
        const remainingWordLength            = countRemainingWordLength(this);
        remainingWordLengthDisplay.innerHTML = `${remainingWordLength} characters remaining`;
    });

    const initialRemainingWordLength     = countRemainingWordLength(reasonInput);
    remainingWordLengthDisplay.innerHTML = `${initialRemainingWordLength} characters remaining`;
}

function createSubmitButton(orderId, orderTotalNumber, reasonInputWrapper) {
    const submitButton       = document.createElement('button');
    submitButton.textContent = 'Submit';
    submitButton.className   = 'btn btn-primary submit';

    submitButton.addEventListener('click', function() {
        submitRefund(orderId, orderTotalNumber, reasonInputWrapper);
    });

    return submitButton;
}

function createCancelButton(reasonInputWrapper) {
    const cancelButton       = document.createElement('button');
    cancelButton.textContent = 'Cancel';
    cancelButton.className   = 'btn btn-secondary';

    cancelButton.addEventListener('click', function() {
        cancelRefund(reasonInputWrapper);
    });

    return cancelButton;
}

function submitRefund(orderId, orderTotalNumber, reasonInputWrapper) {
    const reasonInput   = reasonInputWrapper.querySelector('textarea');
    const amount        = orderTotalNumber;
    const reason        = reasonInput.value;

    showLoader();

    $.ajax({
        type: 'POST',
        cache: false,
        dataType: 'json',
        url: adminajax_link,
        data: {
            ajax: true,
            action: 'fullRefund',
            RefundAmount: amount,
            Refundreason: reason,
            OrderId: orderId,
        },
        success: function(data) {
            handleRefundResponse(data, reasonInput, reasonInputWrapper);
        },
        error: function() {
            handleRefundError(reasonInput, reasonInputWrapper);
        }
    });
}

function handleRefundResponse(data, reasonInput, reasonInputWrapper) {
    const response = JSON.parse(data);
    const divElement = reasonInput.closest('.full-refund-card');

    if (response.status === 'success') {
        $('#ajax_confirmation').html('A Full Refund was Successfully Created.').show();
        // Clear the input fields
        reasonInput.value = '';   
        divElement.style.display = 'none';
        hideLoader(); 
        setTimeout(function(){
            window.location.reload();
        }, 2000);    
    } else {
        $('#ajax_confirmation').html('The Full Refund Failed.').show();
        $('#ajax_confirmation').css({'color': '#363a41','background-color': '#fbc6c3','border': '1px solid #f44336'});
        $('#ajax_confirmation').removeClass('alert-success').addClass('alert-danger');
        reasonInput.value = '';   
        divElement.style.display = 'none';
        hideLoader(); 
        setTimeout(function(){
            window.location.reload();
        }, 2000); 
    }
}

function handleRefundError(reasonInput, reasonInputWrapper) {
    showErrorMessage('Error : Full Refund Failed.');
    resetForm(reasonInput, reasonInputWrapper);
}

function resetForm(reasonInput, reasonInputWrapper, divElement = null) {
    reasonInput.value = '';
    reasonInputWrapper.querySelector('span').innerHTML = '100 characters remaining';
    if (divElement) divElement.style.display = 'none';
    hideLoader();
}

function cancelRefund(reasonInputWrapper) {
    reasonInputWrapper.querySelector('textarea').value = '';
    reasonInputWrapper.closest('.full-refund-card').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const paymentMethod = document.querySelector('.table[data-role="payments-grid-table"] tbody tr [data-role="payment-method-column"]')?.textContent.trim();
    if (paymentMethod === 'Mastercard Payment Gateway Services - Simplify') {
        const fullRefundElement = document.querySelector('.partial-refund-display');
        if (fullRefundElement) fullRefundElement.style.display = fullRefundElement.style.display === 'none' ? 'block' : 'none';
    }
});

function showLoader() {
    // Create and display the loader
    const loader = document.createElement('div');
    loader.className = 'loader';
    document.body.appendChild(loader);
}

function hideLoader() {
    // Hide the loader
    const loader = document.querySelector('.loader');
    if (loader) {
        document.body.removeChild(loader);
    }
}

function countRemainingWordLength(textarea) {
    // Get the length of the text in the textarea.
    const wordLength = textarea.value.length;
    // Calculate the remaining word length.
    const remainingWordLength = 100 - wordLength;
    return remainingWordLength;
}
