@php
    $pageTitle='Office Login'; $portalName='Office Portal'; $portalDescription='Sign in to manage clearance for your office.'; $roleIcon='bi-buildings';
    $submitRoute=route('office.login.submit'); $recoveryPortal='office';
    $loginName='login'; $loginType='text'; $loginLabel='Email or Personnel ID'; $loginPlaceholder='Email or Personnel ID'; $loginIcon='bi-person';
    $roleName='role'; $roleLabel='Office Role'; $rolePlaceholder='Select your office role'; $showRemember=true;
    $roleOptions=['program_head_bsit'=>'Program Head — BSIT','program_head_bsed'=>'Program Head — BSED','program_head_beed'=>'Program Head — BEED','program_head_bsba'=>'Program Head — BSBA','program_head_bshm'=>'Program Head — BSHM','property_custodian'=>'Property Custodian','scc_adviser'=>'SSC Adviser','sas_director'=>'SAS Director','guidance'=>'Guidance Office','library'=>'Library'];
@endphp
@include('auth.portal-login')
