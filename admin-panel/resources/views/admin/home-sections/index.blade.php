@extends('layouts.admin')

@section('title', 'Home Section Management')
@section('header', 'Home Section Management')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Home Section Management</h1>
            <p>Control the live homepage structure, order, and curated content sections.</p>
        </div>
        <a href="{{ route('admin.home-sections.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Home Section
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">Total Sections</div>
            <div class="fs-2 fw-bold">{{ $summary['total'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">Active Live Sections</div>
            <div class="fs-2 fw-bold">{{ $summary['active'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">Built-in Sections</div>
            <div class="fs-2 fw-bold">{{ $summary['built_in'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">Curated Sections</div>
            <div class="fs-2 fw-bold">{{ $summary['dynamic'] }}</div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-1 fw-bold">Homepage Order</h5>
            <p class="text-muted mb-0 small">Drag sections to reorder the live homepage. Built-in sections can be reordered but not deleted.</p>
        </div>
        <a href="{{ route('admin.settings.homepage') }}" class="btn btn-outline-secondary btn-sm">Back to Homepage Content</a>
    </div>
    <div class="p-4">
        <div class="list-group" id="sectionOrderList">
            @foreach($sections as $section)
                <div class="list-group-item rounded-3 mb-3 border" draggable="true" data-token="{{ $section['token'] }}">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="d-flex gap-3 align-items-start">
                            <span class="text-muted fs-5" style="cursor: grab;"><i class="fas fa-grip-vertical"></i></span>
                            <div>
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span class="badge text-bg-light">#{{ $section['sort_order'] }}</span>
                                    <span class="fw-semibold">{{ $section['title'] }}</span>
                                    <span class="badge {{ $section['source'] === 'built_in' ? 'text-bg-secondary' : 'text-bg-primary' }}">{{ ucfirst(str_replace('_', ' ', $section['source'])) }}</span>
                                    <span class="badge {{ $section['is_active'] ? 'text-bg-success' : 'text-bg-warning' }}">{{ $section['is_active'] ? 'Live' : 'Hidden' }}</span>
                                </div>
                                <div class="text-muted small mt-1">{{ $section['subtitle'] ?: 'No subtitle set.' }}</div>
                                <div class="text-muted small mt-1">Type: {{ \App\Models\HomeSection::TYPES[$section['type']] ?? ucwords(str_replace('_', ' ', $section['type'])) }}</div>
                            </div>
                        </div>
                        @if($section['source'] === 'dynamic')
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.home-sections.edit', $section['model']) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.home-sections.destroy', $section['model']) }}" onsubmit="return confirm('Delete this home section?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.home-sections.reorder') }}" id="reorderForm">
            @csrf
            <div id="orderedTokensFields"></div>
            <button type="submit" class="btn btn-primary">Save Section Order</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('sectionOrderList');
    const reorderForm = document.getElementById('reorderForm');
    const orderedTokensFields = document.getElementById('orderedTokensFields');
    if (!list) {
        return;
    }

    let draggedItem = null;

    list.querySelectorAll('[draggable="true"]').forEach((item) => {
        item.addEventListener('dragstart', () => {
            draggedItem = item;
            item.classList.add('opacity-50');
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('opacity-50');
            draggedItem = null;
        });

        item.addEventListener('dragover', (event) => {
            event.preventDefault();
        });

        item.addEventListener('drop', (event) => {
            event.preventDefault();
            if (!draggedItem || draggedItem === item) {
                return;
            }

            const listItems = Array.from(list.children);
            const draggedIndex = listItems.indexOf(draggedItem);
            const targetIndex = listItems.indexOf(item);

            if (draggedIndex < targetIndex) {
                item.after(draggedItem);
            } else {
                item.before(draggedItem);
            }
        });
    });

    reorderForm?.addEventListener('submit', function () {
        orderedTokensFields.innerHTML = '';
        Array.from(list.children).forEach((item) => {
            const token = item.dataset.token;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ordered_tokens[]';
            input.value = token;
            orderedTokensFields.appendChild(input);
        });
    });
});
</script>
@endsection
