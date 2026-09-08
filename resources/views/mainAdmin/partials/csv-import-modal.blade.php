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
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="btn-save"><i class="bi bi-file-earmark-arrow-up-fill"></i> Upload CSV</button>
                    <small style="color:var(--muted);">CSV columns must use header names: <code>student_id,email,firstname,lastname,password,etc.</code></small>
                </div>
            </form>
        </div>
    </div>
</div>
