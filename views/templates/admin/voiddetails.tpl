{if isset($voidDetails)}
    <div class="card">
    <div class="card-header">
        <h3>{l s='Void Details'}</h3>
    </div>
    <div class="card-body">
        <p><strong>{l s='Order ID:'}</strong> {$voidDetails.order_id}</p>
        <p><strong>{l s='Transcation ID:'}</strong> {$voidDetails.transcation_id}</p>
        <p><strong>{l s='Amount:'}</strong> {$voidDetails.amount}</p>
        <p><strong>{l s='Date:'}</strong> {$voidDetails.date_created}</p>
    </div>
    </div>
{/if}
