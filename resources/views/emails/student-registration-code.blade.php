<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#eef6ff;font-family:Arial,sans-serif;color:#102a56">
    <div style="max-width:560px;margin:0 auto;padding:30px;border:1px solid #d5e6fa;border-radius:18px;background:#ffffff">
        <h1 style="margin:0 0 14px;font-size:24px">Register your student account</h1>
        <p>Hello,</p>
        <p>Someone started registering a ClearanceMS student account for <strong>{{ $msAccount }}</strong> (Student ID <strong>{{ $studentId }}</strong>). Enter this code to continue:</p>
        <p style="margin:24px 0;font-size:34px;font-weight:700;letter-spacing:8px;color:#075bea">{{ $code }}</p>
        <p>This code expires in {{ $expiresInMinutes }} minutes and can be used only once.</p>
        <p style="color:#5a6f90">If you did not start this, you can ignore this email &mdash; no account is created until the code is entered.</p>
    </div>
</body>
</html>
