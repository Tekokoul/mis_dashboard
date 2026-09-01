<?php
//debug($data['files']);
$status = display_list_element($data['model']['common']['idStatus'], $data['data']['idStatus'], true);
switch (trim($status)){
    case "NEW":
        $class = 'success';
        break;
    case 'OPEN':
        $class = 'info';
        break;
    case 'REJECTED':
        $class = 'warning';
        break;
    case 'CLOSED':
        $class = 'danger';
        break;
}
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("cases/list/")?>" ><?= display($data['meta_name']); ?></a></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Edit mode</span></li>
            <li><span>Entry: <?= display($data['data']['id']); ?></span></li>
        </ol>
    </div>
    <?php
    if((($_SESSION['user']['group']['cases_write'])&&($data['data']['idStatus']<30))||(($_SESSION['user']['group']['cases_edit_closed'])&&($data['data']['idStatus']>=30))){
    ?>
    <div class="btn-group" style="padding-right: 1em !important;">
        <button type="button" class="btn btn-<?=$class?> btn-px-4 py-3 line-height-1"><?=trim($status)?></button>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-<?=$class?> dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="sr-only">Toggle Dropdown</span>
            </button>
            <div class="dropdown-menu" style="">
                <a class="dropdown-item" href="<?=$this->L("cases/change_status/".$data['data']['id']."/10")?>">NEW</a>
                <a class="dropdown-item" href="<?=$this->L("cases/change_status/".$data['data']['id']."/20")?>">OPEN</a>
                <a class="dropdown-item" href="<?=$this->L("cases/change_status/".$data['data']['id']."/30")?>">REJECTED</a>
                <a class="dropdown-item" href="<?=$this->L("cases/change_status/".$data['data']['id']."/40")?>">CLOSED</a>
                <!--                    <div role="separator" class="dropdown-divider"></div>-->
                <!--                    <a class="dropdown-item" href="#">Separated link</a>-->
            </div>
        </div>
    </div>
    <?php
    }
    ?>
</header>
<form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("cases/edit_update")?>" method="post">
    <input type="hidden" name="tablename" value="<?= display($data['model_name']); ?>" >
    <input type="hidden" name="id" value="<?= $data['data']['id']; ?>" >

    <div class="row">
    <div class="col col-lg-12 col-md-12">
        <div class="tabs">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item active" role="presentation">
                    <a class="nav-link active" data-bs-target="#case_tab" href="#case_tab" data-bs-toggle="tab"
                       class="text-center"><i class="fas fa-info"></i> Overview</a>
                </li>
                <?php
                if((($_SESSION['user']['group']['cases_write'])&&($data['data']['idStatus']<30))||(($_SESSION['user']['group']['cases_edit_closed'])&&($data['data']['idStatus']>=30))){

                ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" data-bs-target="#edit_tab" href="#edit_tab" data-bs-toggle="tab"
                       class="text-center"><i class="fas fa-pencil-alt"></i> Edit</a>
                </li>
                <?php
                }
                ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" data-bs-target="#access_tab" href="#access_tab" data-bs-toggle="tab"
                       class="text-center"><i class="fas fa-user-lock"></i> Access rights</a>
                </li>
            </ul>
            <div class="tab-content">
                <div id="case_tab" class="tab-pane active">
                    <div class="row mb-4">
                        <div class="col col-lg-6 col-md-12">
                            <section class="card card-modern mb-5">
                                <div class="card-body">
                                    <div class="row">
                                        <h3><span class="badge badge-<?=$class;?>"><?=$status;?></span> <?=$data['data']['title'];?></h3>
                                        <p><?=$data['data']['description'];?></p>
                                        <?php
                                        if($data['data']['translation']!=null){
                                            print '<p><strong>Translation: </strong>'.$data['data']['translation'].'</p>';
                                        }
                                        ?>
                                        <p>
                                            Date: <b><?=display_time($data['data']['dateCreated']);?></b><br>
                                            Type: <b><?=display_list_element($data['model']['common']['idType'], $data['data']['idType'], true);?></b><br>
                                            Author: <b><?=$data['data']['userName']." ".$data['data']['userSurname']?></b><br>
                                            Source: <b><?=display_list_element($data['model']['common']['caseSource'], $data['data']['caseSource'], true);?></b><br>
                                            Level: <b><?=display_list_element($data['model']['common']['level'], $data['data']['level'], true);?></b><br>
                                            Primary Issue: <b><?=display_list_element($data['model']['common']['idIssues_1'], $data['data']['idIssues_1'], true);?></b><br>
                                            Secondary Issue: <b><?=display_list_element($data['model']['common']['idIssues_2'], $data['data']['idIssues_2'], true);?></b></p>
                                            <hr>
                                        <p>
                                            User: <b><?=$data['data']['userName']." ".$data['data']['userSurname']?></b><br>
                                            Constituency: <b><?=display_list_element($data['model']['common']['userConstituency'], $data['data']['userConstituency'], true);?></b> | Ward: <b><?=display_list_element($data['model']['common']['userWard'], $data['data']['userWard'], true);?></b><br>
                                        </p>
                                    </div>
                                    <div class="row pt-3">
                                    <!-- BEGIN Timeline Embed -->
                                    <div id="timeline-embed"></div>
                                    <script type="text/javascript">
                                        var timeline_config = {
                                            type: "timeline",
                                            height: "220",
                                            source: {
                                                "timeline": {
                                                    "type":"default",
                                                    "startDate": "<?=display_time($data['data']['dateCreated'],"Y,m,d")?>",
                                                    "date": [
                                                        <?php
                                                        foreach ($data['data']['details'] as $journal){
                                                        ?>
                                                        {
                                                            "startDate":"<?=display_time($journal['event_date'],"Y,m,d")?>",
                                                            "headline":"Case <?=$journal['type'];?>"
                                                        },
                                                        <?php
                                                        }
                                                        ?>
                                                    ]
                                                }
                                            },
                                            debug: true
                                        }
                                    </script>
                                    <!-- END Timeline Embed-->
                                    </div>
                                    <?php
                                    if(!empty($data['files'])){
                                        foreach ($data['files'] as $file){
                                            ?>
                                            <div class="row">
                                                <img src="<?="https://next.botswanaspeaks.gov.bw".DS."media".DS."cases".DS.$data['data']['id'].DS.$file;?>">
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>
                            </section>
                        </div>

                        <div class="col col-lg-6 col-md-12">
                                <section class="card card-modern mb-5">
                                    <div class="card-body">
                                        <div class="timeline timeline-simple mb-3">
                                            <div class="tm-body">
                                                <div class="tm-title">
                                                    <h5 class="m-0 pt-2 pb-2 text-dark font-weight-semibold text-uppercase">Case Timeline</h5>
                                                </div>
                                                <ol class="tm-items">
                                        <?php
                                        foreach ($data['data']['details'] as $detail){
                                            switch ($detail['type']){
                                                case "reply":
                                                    $icon = "bx-message-detail";
                                                    switch ($detail['method']){
                                                        case 1:
                                                            $method = "SMS";
                                                            break;
                                                        case 2:
                                                            $method = "E-mail";
                                                            break;
                                                        case 3:
                                                            $method = "SMS/E-mail";
                                                            break;
                                                    }
                                                    $title = "Reply by ".$method;
                                                    $desc_list = [];
                                                    $desc_list[] = $detail['message'];
                                                    $desc_list[] = "Method: ".$method;
                                                    $desc = implode("<br>", $desc_list);
                                                    $action = "reply";
                                                    break;
                                                case "action":
                                                    $icon = "bx-run";
                                                    $title = $detail['title'];
                                                    $desc_list = [];
                                                    $desc_list[] = $detail['message'];
                                                    $desc_list[] = "Response: ".$detail['method_title'];
                                                    $desc = implode("<br>", $desc_list);
                                                    $action = "action";
                                                    break;
                                                case "redirect":
                                                    $icon = "bx-shuffle";
                                                    $title = $detail['method_title'] ?? "N/A";
                                                    $desc_list = [];
                                                    $desc_list[] = "Redirect to: ".$detail['referral_title'];
                                                    $desc_list[] = "Reason: ".$detail['reason'] ?? "N/A";
                                                    $desc_list[] = "Notes: ".$detail['message'];
                                                    $desc = implode("<br>", $desc_list);
                                                    $action = "redirect";
                                                    break;
                                                case "change":
                                                    $icon = "bxs-edit-alt";
                                                    $title = $detail['title'];
                                                    $desc_list = [];
                                                    $desc = implode("<br>", $desc_list);
                                                    $action = "change";
                                                    break;
                                                case "comment":
                                                    $icon = "bx-message-add";
                                                    $title = "Comment";
                                                    $desc_list = [];
                                                    $desc_list[] = $detail['message'];
                                                    $desc = implode("<br>", $desc_list);
                                                    $action = "comment";
                                                    break;
                                            }
                                        ?>


                                                        <li>
                                                            <div class="tm-box">
                                                                <p class="text-muted mb-0"><?=display_time($detail['event_date'])?></p>
                                                                <h4><i class="bx <?=$icon;?> text-3 me-2"></i> <?=$title;?></h4>
                                                                <p><?=$desc?>
                                                                    <?php
                                                                    if($detail['type']!="change"){
                                                                        if((($_SESSION['user']['group']['cases_extras'])&&($data['data']['idStatus']<30))||(($_SESSION['user']['group']['cases_edit_closed'])&&($data['data']['idStatus']>=30))){

                                                                        ?>
                                                                    <span class="pull-right">
                                                <a href="#" data-id="<?=$detail['id'];?>" data-case="<?=$data['data']['id'];?>" data-type="<?=$action;?>" class="open-modal"><i class="bx bx-pencil text-3 me-2"></i></a>
                                                <a class="event-delete-modal" data-id="<?=$detail['id'];?>" data-case="<?=$data['data']['id'];?>" data-type="<?=$action;?>" href="#deleteModal"><i class="bx bx-trash text-3 me-2"></i></a>
                                                                    </span><?php
                                                                    }
                                                                    }
                                                                    ?>
                                                                </p>
                                                            </div>
                                                        </li>
                                            <?php
                                        }
                                        ?>
                                                </ol>

                                            </div>
                                        </div>
                                    </div>
                                </section>
                        </div>

                    </div>
                </div>
                <?php
                if($_SESSION['user']['group']['cases_write']){
                ?>
                <div id="edit_tab" class="tab-pane">
                    <div class="col-md-12 col-lg-12">
                            <h4>Step 1 of 3</h4>
                            <div class="form-group row align-items-center pb-3">
                                <div class="col-sm-12">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="idType" id="inlineRadio1" value="10" <?=($data['data']['idType']==10) ? "checked" : "";?>>
                                        <label class="form-check-label" for="inlineRadio1">Opinion</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="idType" id="inlineRadio2" value="21" <?=($data['data']['idType']==21) ? "checked" : "";?>>
                                        <label class="form-check-label" for="inlineRadio2">Request For Action</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="idType" id="inlineRadio3" value="20" <?=($data['data']['idType']==20) ? "checked" : "";?>>
                                        <label class="form-check-label" for="inlineRadio3">Request For Information</label>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <h4>Step 2 of 3 | User Information</h4>
                                <div class="row form-group pb-3">
                                    <div class="col-lg-1 col-sm-12 ">

                                    <label for="register-form-name">Title:</label>
                                    <select id="jform_userTitle" name="usertitle" class="form-select form-control form-control-modern" disabled>
                                        <option value="">Select title</option>
                                        <option value="1" <?=($data['data']['userTitle']==1) ? "selected='selected'" : "";?>>Mr.</option>
                                        <option value="2" <?=($data['data']['userTitle']==2) ? "selected='selected'" : "";?>>Ms.</option>
                                        <option value="3" <?=($data['data']['userTitle']==3) ? "selected='selected'" : "";?>>Mrs.</option>
                                        <option value="4" <?=($data['data']['userTitle']==4) ? "selected='selected'" : "";?>>Dr.</option>
                                        <option value="6" <?=($data['data']['userTitle']==6) ? "selected='selected'" : "";?>>Professor</option>
                                    </select>
                                    </div>
                                <div class="col-lg-5 col-sm-12 ">
                                    <label for="register-form-name">First Name*:</label>
                                    <input type="text" name="firstName" id="register-form-name" value="<?=$data['data']['userName'];?>" class="form-control form-control-modern" required disabled/>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-name">Last Name*:</label>
                                    <input type="text" name="lastName" id="register-form-name" value="<?=$data['data']['userSurname'];?>" class="form-control form-control-modern" required disabled/>
                                </div>
                                </div>
                            <div class="row form-group pb-3">

                            <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-address">Address:</label>
                                    <input type="text" name="address" id="register-form-address" value="<?=$data['data']['userAddress'];?>" class="form-control form-control-modern" disabled/>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-email">Email:</label>
                                    <input type="text" name="email" id="register-form-email" value="<?=$data['data']['userEmail'];?>" class="form-control form-control-modern" disabled/>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-email">Constituency:</label>
                                    <select class="form-select form-control form-control-modern" name="userConstituency" id="constituencies" aria-invalid="false">
                                        <?php foreach ($data['constituencies'] as $constituency) {
                                            print '<option value="'.$constituency['id']. '"';
                                            if($constituency['id']==$data['data']['userConstituency']) {print 'selected';}
                                            print '>'.$constituency['name'] .'</option>';
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-email">Ward:</label>
                                    <select class="form-select form-control form-control-modern" name="userWard" id="wards" aria-invalid="false">
                                        <?php foreach ($data['wards'] as $ward) {
                                            print '<option value="'.$ward['id']. '"';
                                            if($ward['id']==$data['data']['userWard']) {print 'selected';}
                                            print '>'.$ward['name'] .'</option>';
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-email">Represents:</label>
                                    <select class="form-select form-control form-control-modern" name="represents" id="represents" aria-invalid="false" disabled>
                                        <option value="1" <?=($data['data']['representsGroup']==1) ? "selected='selected'" : "";?>>Individual</option>
                                        <option value="2" <?=($data['data']['representsGroup']==2) ? "selected='selected'" : "";?>>Group</option>
                                        <option value="3" <?=($data['data']['representsGroup']==3) ? "selected='selected'" : "";?>>Community</option>
                                    </select>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-groupName">Group Name:</label>
                                    <input type="text" name="groupName" id="register-form-groupName" value="<?=$data['data']['groupName'];?>" class="form-control form-control-modern" disabled/>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-mobile">Cellphone Number:</label>
                                    <input type="text" name="mobile" id="register-form-mobile" value="<?=$data['data']['userMobile'];?>" class="form-control form-control-modern" disabled/>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-telephone">Telephone Number:</label>
                                    <input type="text" name="telephone" id="register-form-telephone" value="<?=$data['data']['userTelephone'];?>" class="form-control form-control-modern" disabled/>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-birthDate">Birth Date:</label>
                                    <input type="text" name="birthDate" id="register-form-birthdate" value="<?=$data['data']['userBirthDate'];?>" class="form-control form-control-modern text-left component-datepicker past-enabled" placeholder="dd/mm/yyyy" disabled>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-gender">Gender:</label>
                                    <select class="form-select form-control form-control-modern" name="gender" aria-invalid="false" disabled>
                                        <option value="0" <?=($data['data']['userGender']==0) ? "selected='selected'" : "";?>>Select gender</option>
                                        <option value="1" <?=($data['data']['userGender']==1) ? "selected='selected'" : "";?>>Female</option>
                                        <option value="2" <?=($data['data']['userGender']==2) ? "selected='selected'" : "";?>>Male</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-language">Language:</label>
                                    <select class="form-select form-control form-control-modern" name="language" aria-invalid="false" disabled>
                                        <option value="1">English</option>
                                        <option value="2">Setswana</option>
                                    </select>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-otherLanguage">Other Language:</label>
                                    <input type="text" name="otherLanguage" id="register-form-otherLanguage" value="<?=$data['data']['userLanguageOther'];?>" class="form-control form-control-modern" disabled/>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-12">
                                    <p>Place a pin on the map (to move a pin, left click on the map)</p>
                                    <script>
                                        function initMap() {
                                            const map = new google.maps.Map(document.getElementById("map"), {
                                                center: { lat: -34.397, lng: 150.644 },
                                                zoom: 8,
                                            });
                                        }
                                    </script>
                                    <div id="map" style="height: 400px;"></div>
                                </div>
                            </div>
                            <hr>
                            <h4>Step 3 of 3 | Case & Issue Details</h4>
                            <div class="row form-group pb-3">

                            <div class="col-12">
                                    <label for="register-form-title">Title*:</label>
                                    <input type="text" name="title" id="register-form-title" value="<?=$data['data']['title'];?>" class="form-control form-control-modern" required disabled/>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-primaryIssue">Primary Issue*:</label>
                                    <select class="form-select form-control form-control-modern" name="idIssues_1" aria-invalid="false" required>
                                        <option value="" selected="selected">Select primary issue</option>
                                        <?php foreach ($data['issues'] as $issue) {
                                            print '<option value="'.$issue['id']. '" title="'.$issue['description'].'" ';
                                            if($issue['id']==$data['data']['idIssues_1']) { print 'selected';}
                                            print '>'.$issue['title'] .'</option>';
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-secondaryIssue">Secondary Issue:</label>
                                    <select class="form-select form-control form-control-modern" name="idIssues_2" aria-invalid="false">
                                        <option value="" selected="selected">Select secondary issue</option>
                                        <?php foreach ($data['issues'] as $issue) {
                                            print '<option value="'.$issue['id']. '" title="'.$issue['description'].'" ';
                                            if($issue['id']==$data['data']['idIssues_2']) { print 'selected';}
                                            print '>'.$issue['title'] .'</option>';
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row form-group pb-3">
                                <div class="col-lg-6 col-sm-12">
                                    <label for="register-form-level">Level*:</label>
                                    <select class="form-select form-control form-control-modern" name="level" aria-invalid="false" required>
                                        <option value="">Select level</option>
                                        <option value="2" <?=($data['data']['level']==2) ? "selected" : "";?>>Communal</option>
                                        <option value="3" <?=($data['data']['level']==3) ? "selected" : "";?>>National</option>
                                        <option value="1" <?=($data['data']['level']==1) ? "selected" : "";?>>Personal</option>
                                    </select>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <label for="category">Category:</label>
                                    <select class="form-select form-control form-control-modern" name="category" aria-invalid="false">
                                        <option value="0" <?=($data['data']['category']==0) ? "selected='selected'" : "";?>>Select category</option>
                                        <option value="3" <?=($data['data']['category']==3) ? "selected='selected'" : "";?>>Approval</option>
                                        <option value="2" <?=($data['data']['category']==2) ? "selected='selected'" : "";?>>Complaint</option>
                                        <option value="1" <?=($data['data']['category']==1) ? "selected='selected'" : "";?>>Policy Recommendation</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                                <div class="col-12 col-sm-12">
                                    <label for="register-form-description">Description*:</label>
                                    <textarea class="form-control form-control-modern" id="description" name="description" rows="4" required disabled><?=$data['data']['description'];?></textarea>
                                </div>
                            </div>
                            <div class="row form-group pb-3">

                            <div class="col-12 col-sm-12" id="remedyfield">
                                    <label for="remedy">Remedy:</label>
                                    <textarea class="form-control form-control-modern" id="remedy" name="remedy" rows="4" disabled><?=$data['data']['remedy'];?></textarea>
                                </div>
                            </div>
                            <div class="row form-group pb-5">

                                <div class="col-12 col-sm-12">
                                    <label for="register-form-translation">Translation:</label>
                                    <textarea class="form-control form-control-modern" id="translation" name="translation" rows="4"><?=$data['data']['translation'];?></textarea>
                                </div>
                            </div>
                    </div>
                </div>
                <?php
                }
                ?>
                <div id="access_tab" class="tab-pane">
                    <div class="row mb-4">
                        <div class="col col-lg-12 col-md-12">
                            <section class="card card-modern mb-5">
                                <div class="card-body">
                                    <div class="row pb-3">
                                        <div>
                                        This is a list of all the users that have access to this case, based on their account type:
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div>
                                            <?php
                                                foreach ($data['data']['access_users'] as $user){
                                                    print $user['name']." [".($user['username'] ?? 'N/A')."]<br>";
                                                }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row action-buttons nopadding">
        <div class="col-12 col-md-auto">
            <?php
            if(isset($data['meta_actions'])){
            if((($_SESSION['user']['group']['cases_extras'])&&($data['data']['idStatus']<30))||(($_SESSION['user']['group']['cases_edit_closed'])&&($data['data']['idStatus']>=30))){

                foreach ($data['meta_actions'] as $action) {
                    $show_action = true;
//                    $show_action = false;

//                    if (isset($action['condition'])) {
//                        if (ce_compare_values($data['data'][$action['condition']['field']], $action['condition']['operator'], $action['condition']['value'])) {
//                            $show_action = true;
//                        }
//                    } else {
//                        $show_action = true;
//                    }
                    if ($show_action) {
                        print '<a href="' . $this->model->dynamic_link($action['link'], $data['data']) . '" data-id="new" data-case="' . $data['data']['id'] . '" data-type="' . $action['type'] . '" class="btn btn-default btn-px-4 py-3 line-height-1 me-2 open-modal" target="' . $action['target'] . '">
                <i class="bx ' . $action['icon'] . ' text-4 me-2"></i> ' . $action['title'] . '
            </a>';
                    }
                    }
                }
            }
            ?>

<!--            <a href="#" class="delete-button btn btn-danger btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1">-->
<!--                <i class="bx bx-trash text-4 me-2"></i> Delete Product-->
<!--            </a>-->
        </div>
        <div class="col-12 col-md-auto ms-md-auto mt-3 mt-md-0 ms-auto">
            <?php
            if($_SESSION['user']['group']['cases_write']){
            ?>
            <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1" data-loading-text="Loading...">
                <i class="bx bx-save text-4 me-2"></i> Update
            </button>
            <?php
            }
            ?>

        </div>
        <div class="col-12 col-md-auto px-md-0 mt-3 mt-md-0">
            <a href="<?=$this->L("cases/list");?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1">Back</a>
        </div>
    </div>
</form>
<div id="caseModal" class="modal-block modal-block-primary mfp-hide"></div>
<div id="deleteModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
        <header class="card-header">
            <h2 class="card-title">Are you sure?</h2>
        </header>
        <div class="card-body">
            <div class="modal-wrapper">
                <div class="modal-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="modal-text">
                    <p class="mb-0">Are you sure that you want to delete this entry?</p>
                </div>
            </div>
        </div>
        <footer class="card-footer">
            <div class="row">
                <div class="col-md-12 text-end">
                    <button class="btn btn-primary modal-confirm">Confirm</button>
                    <button class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </div>
        </footer>
    </section>
</div>