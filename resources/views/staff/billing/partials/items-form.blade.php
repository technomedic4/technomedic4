@php
    $formItems = old('items', $formItems);
@endphp

<div class="staff-billing-items">
    <div class="staff-panel-heading">
        <div>
            <h2>Rincian tagihan</h2>
            <p>Masukkan item dan harga dalam rupiah. Total dihitung otomatis oleh sistem.</p>
        </div>
        <button class="staff-secondary-button" type="button" data-add-bill-item>+ Tambah item</button>
    </div>
    <div class="staff-billing-item-list" data-bill-items>
        @foreach ($formItems as $index => $item)
            <div class="staff-billing-item-row" data-item-index="{{ $index }}">
                <div class="staff-form-field">
                    <label for="item_name_{{ $index }}">Nama item <span>*</span></label>
                    <input id="item_name_{{ $index }}" name="items[{{ $index }}][item_name]" type="text" maxlength="150" value="{{ $item['item_name'] ?? '' }}" @readonly(! empty($item['nursing_treatment_id'])) required>
                    <input type="hidden" name="items[{{ $index }}][nursing_treatment_id]" data-nursing-treatment-id value="{{ $item['nursing_treatment_id'] ?? '' }}">
                </div>
                <div class="staff-form-field">
                    <label for="quantity_{{ $index }}">Jumlah <span>*</span></label>
                    <input id="quantity_{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="1" max="10000" value="{{ $item['quantity'] ?? 1 }}" required>
                </div>
                <div class="staff-form-field">
                    <label for="unit_price_{{ $index }}">Harga satuan (Rp) <span>*</span></label>
                    <input id="unit_price_{{ $index }}" name="items[{{ $index }}][unit_price]" type="number" min="1" max="999999999999" value="{{ $item['unit_price'] ?? '' }}" required>
                </div>
                <button class="staff-small-action is-danger" type="button" data-remove-bill-item aria-label="Hapus item">Hapus</button>
            </div>
        @endforeach
    </div>
</div>

<template data-bill-item-template>
    <div class="staff-billing-item-row" data-item-index="__INDEX__">
        <div class="staff-form-field">
            <label for="item_name___INDEX__">Nama item <span>*</span></label>
            <input id="item_name___INDEX__" name="items[__INDEX__][item_name]" type="text" maxlength="150" required>
            <input type="hidden" name="items[__INDEX__][nursing_treatment_id]" data-nursing-treatment-id>
        </div>
        <div class="staff-form-field">
            <label for="quantity___INDEX__">Jumlah <span>*</span></label>
            <input id="quantity___INDEX__" name="items[__INDEX__][quantity]" type="number" min="1" max="10000" value="1" required>
        </div>
        <div class="staff-form-field">
            <label for="unit_price___INDEX__">Harga satuan (Rp) <span>*</span></label>
            <input id="unit_price___INDEX__" name="items[__INDEX__][unit_price]" type="number" min="1" max="999999999999" required>
        </div>
        <button class="staff-small-action is-danger" type="button" data-remove-bill-item aria-label="Hapus item">Hapus</button>
    </div>
</template>

<script>
    (() => {
        const list = document.querySelector('[data-bill-items]');
        const template = document.querySelector('[data-bill-item-template]');
        const addButton = document.querySelector('[data-add-bill-item]');
        let nextIndex = 0;

        if (!list || !template || !addButton) {
            return;
        }

        nextIndex = Array.from(list.children).reduce(
            (next, row) => Math.max(next, Number(row.dataset.itemIndex) + 1),
            0,
        );

        const addItem = () => {
            const itemIndex = nextIndex++;
            list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(itemIndex)));
        };

        addButton.addEventListener('click', addItem);
        list.addEventListener('click', (event) => {
            if (event.target.closest('[data-remove-bill-item]')) {
                event.target.closest('.staff-billing-item-row').remove();

                if (list.children.length === 0) {
                    addItem();
                }
            }
        });
    })();
</script>
