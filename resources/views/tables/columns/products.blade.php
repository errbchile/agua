<div>
    <ul>
        @foreach ($getRecord()->orderProducts as $product)
            <li class="text-xs">
                {{ $product->quantity }} {{ $product->product->name }}
            </li>
        @endforeach
    </ul>
</div>
