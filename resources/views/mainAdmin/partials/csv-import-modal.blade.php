<div class="modal-overlay" id="csvImportModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-filetype-csv" style="color:var(--accent2);margin-right:8px;"></i> Import CSV</h4>
            <button class="close-btn" onclick="closeCsvModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('import.csv') }}" enctype="multipart/form-data" id="csvImportForm">
                @csrf
                <input type="hidden" name="type" id="csvImportType" value="">
                <div class="form-row">
                    <div class="fg" style="width:100%;">
                        <label>Select CSV file</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                        <small style="display:block;margin-top:7px;color:var(--muted);">From Excel, use <strong>Save As → CSV UTF-8 (Comma delimited) (*.csv)</strong>. Do not rename an .xlsx workbook to .csv.</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg" style="width:100%;">
                        <label>Import Type</label>
                        <select id="csvImportTypeSelect" onchange="setCsvType(this.value)" required>
                            <option value="">-- Select type --</option>
                            <option value="students">Students</option>
                            <option value="student_registry">Student Registration List</option>
                            <option value="instructors">Instructors</option>
                            <option value="admin_personnel">Admin Personnel</option>
                            <option value="registrar">Registrar</option>
                            <option value="subject_codes">Subject Codes</option>
                            <option value="sections">Sections</option>
                            <option value="assignments">Assignments</option>
                            <option value="treasurers">Treasurers</option>
                        </select>
                    </div>
                </div>
                <div id="csvImportHeaderGuide" style="margin-bottom:16px;padding:13px 15px;border:1px solid rgba(30,136,229,.2);border-radius:12px;background:rgba(225,243,255,.58);">
                    <strong style="display:block;margin-bottom:6px;color:var(--ink);font-size:13px;"><i class="bi bi-list-check" style="margin-right:6px;color:var(--accent2);"></i>Required headers</strong>
                    <code id="csvRequiredHeaders" style="display:block;color:#185b8f;white-space:normal;overflow-wrap:anywhere;">Select an import type to display its required headers.</code>
                    <small id="csvOptionalHeaders" style="display:none;margin-top:7px;color:var(--muted);"></small>
                </div>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="btn-save"><i class="bi bi-file-earmark-arrow-up-fill"></i> Upload CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>
