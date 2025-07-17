@extends('layouts.admin')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .accordion-body {
        white-space: pre-wrap;
        font-family: monospace;
        font-size: 14px;
        background-color: #fff;
        overflow-x: auto;
        min-height: 50px;
    }

    .accordion-item {
        border-radius: 6px;
        margin-bottom: 10px;
        border: 1px solid #dee2e6;
    }

    .log-line {
        display: block;
        padding: 2px 0;
    }

    .log-info      { color: #198754; }
    .log-warning   { color: #fd7e14; }
    .log-error     { color: #dc3545; font-weight: bold; }
    .log-debug     { color: #0d6efd; }
    .log-critical  { color: #b02a37; font-weight: bold; }
    .log-alert     { color: #d63384; font-weight: bold; }
    .log-emergency { color: #6f42c1; font-weight: bold; }
</style>
@endpush

@section('content')
<div class="container py-4">
    <h2 class="mb-4 fw-bold">📄 Log Viewer</h2>

    <!-- Toolbar -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <select id="logFileDropdown" class="form-select form-select-sm" style="width: 220px;"></select>

        <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" onclick="fetchLogs()">
            <i class="bi bi-download"></i> Load
        </button>

        <button class="btn btn-danger btn-sm d-flex align-items-center gap-1" onclick="clearLogs()">
            <i class="bi bi-trash"></i> Clear
        </button>

        <input type="text" id="logSearch" class="form-control form-control-sm" placeholder="🔍 Search logs..." style="width: 180px;" oninput="filterLogs()">

        <select class="form-select form-select-sm" onchange="jumpToBlock(this.value)" id="jumpSelect" style="width: 180px;">
            <option value="">⏱ Jump to Time</option>
        </select>
    </div>

    <!-- Expand / Collapse -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <button class="btn btn-outline-secondary btn-sm" onclick="expandAll()">Expand All</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="collapseAll()">Collapse All</button>
    </div>

    <!-- Meta -->
    <div class="text-muted small mb-2" id="logMeta"></div>
    <h6 class="text-muted mb-2">File: <span id="selectedFile">Loading...</span></h6>

    <!-- Accordion Content -->
    <div id="logContent">Fetching logs...</div>
</div>
@endsection

@push('scripts')
<script>
let currentFile = '';
let originalGroups = {};

document.addEventListener('DOMContentLoaded', function () {
    const dropdown = document.getElementById('logFileDropdown');
    const logContent = document.getElementById('logContent');
    const selectedFileLabel = document.getElementById('selectedFile');
    const jumpSelect = document.getElementById('jumpSelect');

    loadFileList();

    function loadFileList() {
        fetch('/logs/list')
            .then(res => res.json())
            .then(data => {
                dropdown.innerHTML = '';
                data.files.forEach(file => {
                    const option = document.createElement('option');
                    option.value = file;
                    option.textContent = file;
                    dropdown.appendChild(option);
                });

                if (data.files.length > 0) {
                    currentFile = data.files[0];
                    dropdown.value = currentFile;
                    selectedFileLabel.textContent = currentFile;
                    fetchLogs();
                } else {
                    logContent.innerHTML = '<p>No log files found.</p>';
                }
            })
            .catch(() => {
                logContent.innerHTML = '<p>Failed to load file list.</p>';
            });
    }

    function fetchLogs() {
        const selected = dropdown.value;
        currentFile = selected;
        selectedFileLabel.textContent = selected;

        fetch(`/logs?file=${encodeURIComponent(selected)}`)
            .then(res => res.json())
            .then(data => {
                originalGroups = data.groups || {};
                const meta = data.meta || {};

                document.getElementById('logMeta').textContent =
                    `Size: ${(meta.size / 1024).toFixed(1)} KB · ` +
                    `Lines: ${meta.lines} · ` +
                    `Last Modified: ${meta.last_modified}`;

                renderAccordion(originalGroups);
            })
            .catch(() => {
                logContent.innerHTML = '<p>Error loading log file.</p>';
            });
    }

    window.clearLogs = function () {
        if (!confirm(`Are you sure you want to clear ${currentFile}?`)) return;

        fetch('/clear-logs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ file: currentFile })
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            fetchLogs();
        })
        .catch(() => {
            alert('Error clearing log file.');
        });
    }

    window.filterLogs = function () {
        const term = document.getElementById('logSearch').value.toLowerCase();
        const filtered = {};

        for (const [label, lines] of Object.entries(originalGroups)) {
            const match = lines.filter(line => line.toLowerCase().includes(term));
            if (match.length > 0 || term === '') {
                filtered[label] = match;
            }
        }

        renderAccordion(filtered);
    }

    window.renderAccordion = function (groups) {
        const blocks = Object.keys(groups);
        jumpSelect.innerHTML = '<option value="">⏱ Jump to Time</option>';
        let html = '<div class="accordion" id="logAccordion">';

        blocks.forEach((blockLabel, i) => {
            const blockId = 'block' + i;
            const isLast = i === blocks.length - 1;

            jumpSelect.innerHTML += `<option value="${blockId}">${blockLabel}</option>`;

            const lines = groups[blockLabel].map(line => {
                const safeLine = escapeHtml(line);
                return `<div class="log-line ${getLineClass(line)}">${highlightLine(safeLine)}</div>`;
            }).join('');

            html += `
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-${blockId}">
                        <button class="accordion-button ${isLast ? '' : 'collapsed'}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${blockId}">
                            ${blockLabel}
                        </button>
                    </h2>
                    <div id="collapse-${blockId}" class="accordion-collapse collapse ${isLast ? 'show' : ''}">
                        <div class="accordion-body">${lines}</div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        logContent.innerHTML = html;
    }

    window.getLineClass = function (line) {
        if (line.includes('local.EMERGENCY')) return 'log-emergency';
        if (line.includes('local.ALERT'))     return 'log-alert';
        if (line.includes('local.CRITICAL'))  return 'log-critical';
        if (line.includes('local.ERROR'))     return 'log-error';
        if (line.includes('local.WARNING'))   return 'log-warning';
        if (line.includes('local.INFO'))      return 'log-info';
        if (line.includes('local.DEBUG'))     return 'log-debug';
        return '';
    }

    window.highlightLine = function (line) {
        return line
            .replace('local.EMERGENCY', '🚨 <span class="log-emergency">EMERGENCY</span>')
            .replace('local.ALERT',     '🔔 <span class="log-alert">ALERT</span>')
            .replace('local.CRITICAL',  '🔥 <span class="log-critical">CRITICAL</span>')
            .replace('local.ERROR',     '❌ <span class="log-error">ERROR</span>')
            .replace('local.WARNING',   '⚠️ <span class="log-warning">WARNING</span>')
            .replace('local.INFO',      'ℹ️ <span class="log-info">INFO</span>')
            .replace('local.DEBUG',     '🐞 <span class="log-debug">DEBUG</span>');
    }

    window.escapeHtml = function (unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.expandAll = function () {
        document.querySelectorAll('.accordion-collapse').forEach(el => {
            new bootstrap.Collapse(el, { show: true });
        });
    }

    window.collapseAll = function () {
        document.querySelectorAll('.accordion-collapse.show').forEach(el => {
            new bootstrap.Collapse(el, { toggle: false });
            el.classList.remove('show');
        });
    }

    window.jumpToBlock = function (id) {
        const target = document.getElementById('collapse-' + id);
        if (target) {
            new bootstrap.Collapse(target, { show: true });
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
});
</script>
@endpush
