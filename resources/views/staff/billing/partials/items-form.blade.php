@php
    $formItems = old('items', $formItems);
@endphp

<div class="staff-billing-items">
    <div class="staff-panel-heading">
        <div>
            <h2>Rincian tagihan</h2>
            <p>Pilih tindakan dari katalog tarif. Harga ditetapkan sistem dari database.</p>
        </div>
        @if ($procedureCatalog->isNotEmpty())
            <button class="staff-secondary-button" type="button" data-add-bill-item>+ Tambah tindakan</button>
        @endif
    </div>
    @if ($procedureCatalog->isEmpty())
        <p class="staff-alert staff-alert-info" role="status">Katalog tarif tindakan belum tersedia. Jalankan seeder katalog sebelum membuat tagihan.</p>
    @endif
    <div class="staff-billing-item-list" data-bill-items>
        @foreach ($formItems as $index => $item)
            <div class="staff-billing-item-row" data-item-index="{{ $index }}">
                <div class="staff-form-field">
                    <label for="procedure_catalog_id_{{ $index }}">Tindakan <span>*</span></label>
                    <select id="procedure_catalog_id_{{ $index }}" name="items[{{ $index }}][procedure_catalog_id]" required>
                        <option value="">Pilih tindakan dari katalog</option>
                        @foreach ($procedureCatalog as $procedure)
                            <option value="{{ $procedure->id }}" @selected((string) ($item['procedure_catalog_id'] ?? '') === (string) $procedure->id)>
                                {{ $procedure->name }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="items[{{ $index }}][nursing_treatment_id]" data-nursing-treatment-id value="{{ $item['nursing_treatment_id'] ?? '' }}">
                    @if (! empty($item['nursing_treatment_id']))
                        <small>Tindakan perawat terhubung ke item ini.</small>
                    @endif
                </div>
                <div class="staff-form-field">
                    <label for="quantity_{{ $index }}">Jumlah <span>*</span></label>
                    <input id="quantity_{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="1" max="10000" value="{{ $item['quantity'] ?? 1 }}" required>
                </div>
                <button class="staff-small-action is-danger" type="button" data-remove-bill-item aria-label="Hapus item">Hapus</button>
            </div>
        @endforeach
    </div>
</div>

<template data-bill-item-template>
    <div class="staff-billing-item-row" data-item-index="__INDEX__">
        <div class="staff-form-field">
            <label for="procedure_catalog_id___INDEX__">Tindakan <span>*</span></label>
            <select id="procedure_catalog_id___INDEX__" name="items[__INDEX__][procedure_catalog_id]" required>
                <option value="">Pilih tindakan dari katalog</option>
                @foreach ($procedureCatalog as $procedure)
                    <option value="{{ $procedure->id }}">
                        {{ $procedure->name }}
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="items[__INDEX__][nursing_treatment_id]" data-nursing-treatment-id>
            <small data-nursing-treatment-label></small>
        </div>
        <div class="staff-form-field">
            <label for="quantity___INDEX__">Jumlah <span>*</span></label>
            <input id="quantity___INDEX__" name="items[__INDEX__][quantity]" type="number" min="1" max="10000" value="1" required>
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
