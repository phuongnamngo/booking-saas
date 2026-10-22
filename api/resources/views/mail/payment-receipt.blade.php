<p>Biên nhận {{ $invoice->public_id }}</p>
<p>{{ $invoice->customer_name }}</p>
<p>{{ $invoice->amount }} {{ $invoice->currency }}</p>
@foreach ($invoice->lines as $line)
<p>{{ $line['name'] }} {{ $line['amount'] }}</p>
@endforeach
