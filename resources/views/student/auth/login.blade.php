@php
    $pageTitle = 'Student Login';
    $portalName = 'Student Portal';
    $portalDescription = 'Sign in to continue to your student account.';
    $roleIcon = 'bi-person-lock';
    $submitRoute = route('student.login.submit');
    $recoveryPortal = 'student';
    $loginName = 'student_id';
    $loginType = 'text';
    $loginLabel = 'Student ID';
    $loginPlaceholder = 'Student ID (2000-1234)';
    $loginIcon = 'bi-person';
    $roleOptions = [];
    $roleName = '';
    $roleLabel = '';
    $rolePlaceholder = '';
    $showRemember = true;
    $isStudentPortal = true;
@endphp
@include('auth.portal-login')
