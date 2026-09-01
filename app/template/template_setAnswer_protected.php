<?php
$icon = ($code>=400) ? "fa-exclamation-circle" :"fa-info-circle";
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?=$code.": ".getHTTPcode($code);?></h2>
</header>

<!-- start: page -->
<section class="body-error error-inside">
    <div class="center-error">

        <div class="row">
            <div class="col-lg-8">
                <div class="main-error mb-3">
                    <h2 class="error-code text-dark text-center font-weight-semibold m-0"><?=$code;?> <i class="fas <?=$icon;?>"></i></h2>
                    <p class="error-explanation text-center"><?=$message;?></p>
                </div>
            </div>
            <div class="col-lg-4">
                <h4 class="text">Here are some useful links</h4>
                <ul class="nav nav-list flex-column primary">
                    <li class="nav-item">
                        <a class="nav-link" href="<?=$this->L("");?>"><i class="fas fa-caret-right text-dark"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?=$this->L("users/profile");?>"><i class="fas fa-caret-right text-dark"></i> User Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?=$this->L("support");?>"><i class="fas fa-caret-right text-dark"></i> Support</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
<!-- end: page -->