<?php
if(defined("_WHITELABEL")&&(_WHITELABEL)){
    $logo = '<img src="'._WHITELABEL_LOGO_LIGHT.'" class="logo-image" width="180" alt="'._PROJECT_NAME.'"/>';
    $logo_mobile = _WHITELABEL_LOGO_FAVICON;
    $whitelabel_padding = _WHITELABEL_LOGO_PADDING;
    $logo_style = _WHITELABEL_LOGO_STYLE;
    $about = '';
    $user_logo = _WHITELABEL_LOGO_FAVICON;
} else {
    $logo = '<img src="/media/logo/africacdc_logo_white.png" class="logo-image" width="180" alt="'._PROJECT_NAME.'" />';
    $logo_mobile = '/media/logo/africacdc_favicon.png';
    $logo_style = 'style="margin: 10px 0 0 15px;"';
    $whitelabel_padding = "";
    $about = '<li><a role="menuitem" tabindex="-1" href="'.$this->L("system/about").'"><i class="bx bx-info-circle"></i> About</a></li>';
    $user_logo = '/media/logo/africacdc_favicon.png';
}
?>
<section class="body">
    <header class="header header-nav-menu header-nav-links">
        <div class="logo-container">
            <a href="<?=$this->L("");?>" class="logo" <?=$logo_style;?> <?=$whitelabel_padding;?>>
                <?=$logo?>
                <img src="<?=$logo_mobile?>" class="logo-image-mobile" height="41" alt="<?=_PROJECT_NAME?>" />
            </a>
            <button type="button" class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened" aria-label="Open menu">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
            <div class="header-nav collapse">
                <div class="header-nav-main header-nav-main-effect-1 header-nav-main-sub-effect-1 header-nav-main-square">
                    <nav>
                        <ul class="nav nav-pills" id="mainNav">
                            <li class="">
                                <a class="nav-link disabled" href="#" style="color: <?=_PROJECT_COLOR;?> !important">
                                    <?=_PROJECT_NAME;?>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

        </div>
        <div class="header-right">
            <?php
            if(!_WHITELABEL_HEADER){
                ?>
            <a class="btn search-toggle d-none d-md-inline-block d-xl-none" data-toggle-class="active" data-target=".search"><i class="bx bx-search"></i></a>
            <form action="<?=$this->L("");?>" class="search search-style-1 nav-form d-none d-xl-inline-block">
                <div class="input-group">
                    <input type="text" class="form-control" name="q" id="q" placeholder="Search...">
                    <button class="btn btn-default" type="submit"><i class="bx bx-search"></i></button>
                </div>
            </form>
            <span class="separator"></span>
            <?php
                            }
                            ?>
            <div id="userbox" class="userbox">
                <a href="#" data-bs-toggle="dropdown">
                    <figure class="profile-picture">
                        <img src="<?=$user_logo;?>" alt="<?=display($_SESSION['user']['givenname']." ".$_SESSION['user']['sn']);?>" class="rounded-circle" />
                    </figure>
                    <div class="profile-info">
                        <span class="name"><?=display($_SESSION['user']['givenname']." ".$_SESSION['user']['sn']);?></span>
                        <span class="role"><?=display($_SESSION['user']['group']['name']);?></span>
                    </div>

                    <i class="fa custom-caret"></i>
                </a>

                <div class="dropdown-menu">
                    <ul class="list-unstyled mb-2">
                        <li class="divider"></li>
                        <li>
                            <a role="menuitem" tabindex="-1" href="<?=$this->L("users/profile");?>"><i class="bx bx-user-circle"></i> Profile</a>
                        </li>
                        <li>
                            <a role="menuitem" tabindex="-1" href="<?=$this->L("system/info");?>"><i class="bx bx-server"></i> System Information</a>
                        </li>
                        <?=$about;?>
                        <li>
                            <a role="menuitem" tabindex="-1" href="<?=$this->L("users/logout");?>"><i class="bx bx-power-off"></i> Logout</a>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </header>
    <div class="inner-wrapper">
        <aside id="sidebar-left" class="sidebar-left">
            <div class="sidebar-header">
                <div class="sidebar-toggle d-none d-md-flex" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
                    <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
                </div>
            </div>
            <div class="nano">
                <div class="nano-content">
                    <nav id="menu" class="nav-main" role="navigation">
                        <ul class="nav nav-main">
                            <?php
                            // "You are here". The old test compared the menu link with the raw
                            // request URI, so nothing was lit on a drill-down, an add/edit page,
                            // page 2 of a list or a filtered list. Map those back to the menu
                            // entry they belong to, then match on the path prefix.
                            $here = strtok((string)$this->R->url['request_uri'], '?');
                            foreach (['#^/projects_graphs/(pillar|objective|programme|project)/.*$#' => '/projects_graphs/overview',
                                      '#^/projects/(add|edit)(/.*)?$#'          => '/projects/list',
                                      '#^/projects/progress_edit(/.*)?$#'       => '/projects/progress_list',
                                      '#^/core/db_(add|edit)/([a-z_]+).*$#'     => '/core/db_list/$2',
                                      '#^/users/(add|edit)(/.*)?$#'             => '/users/list'] as $re => $to) {
                                $r = preg_replace($re, $to, $here, 1, $n); if ($n) { $here = $r; break; }
                            }
                            $isHere = fn(string $link): bool => $here === $link || str_starts_with($here, $link.'/');
                            foreach ($this->main_menu as $menu_item=>$properties){
                                if(in_array( $_SESSION['user']['group']['id'], explode(",",$properties['active_for']))) {
                                    $html = "";
                                    switch ($properties['type']){
                                        case "label":
                                            $html = '<li class="nav-group-label">'.$properties['title'].'</li>';
                                            break;
                                        case "link":
                                            $active = $isHere($this->L($properties['link']));
                                            $html = '<li'.($active ? ' class="nav-active"' : '').'><a class="nav-link"'.($active ? ' aria-current="page"' : '').' href="'.$this->L($properties['link']).'"><i class="bx '.$properties['icon'].'" aria-hidden="true"></i><span>'.$properties['title'].'</span></a></li>';
                                            break;
                                        case "parent":
                                            $html = '<li class="nav-parent';
                                            $sub_html = '';
                                            foreach ($properties['nodes'] as $submenu_item => $sub_properties){
                                                $sub_html .= '<li';
                                                $sub_html .= ($this->L($sub_properties['link'])==$this->R->url['request_uri'])? ' class="nav-active"' : '' ;
                                                $html .= ($this->L($sub_properties['link'])==$this->R->url['request_uri'])? ' nav-expanded nav-active' : '' ;
                                                $sub_html .= '><a class="nav-link" href="'.$this->L($sub_properties['link']).'">- '.$sub_properties['title'].'</a></li>';
                                            }
                                            $html .= '"><a class="nav-link" href="#"><i class="bx '.$properties['icon'].'" aria-hidden="true"></i><span>'.$properties['label'].'</span></a><ul class="nav nav-children">';
                                            $html .= $sub_html;
                                            $html .= '</ul></li>';
                                            break;
                                    }
                                    print $html;
                                }
                            }
                            ?>
                        </ul>
                    </nav>
                </div>
                <script>
                    // Maintain Scroll Position
                    if (typeof localStorage !== 'undefined') {
                        if (localStorage.getItem('sidebar-left-position') !== null) {
                            var initialPosition = localStorage.getItem('sidebar-left-position'),
                                sidebarLeft = document.querySelector('#sidebar-left .nano-content');

                            sidebarLeft.scrollTop = initialPosition;
                        }
                    }
                </script>
            </div>
        </aside>
        <section role="main" id="content" tabindex="-1" class="content-body">