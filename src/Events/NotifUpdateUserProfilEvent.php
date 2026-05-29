<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Events/NotifUpdateUserProfil.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 30/05/2025 15:57
 */

namespace App\Events;

use App\Entity\UserProfil;

class NotifUpdateUserProfilEvent
{

    public const ADD_USER_PROFIL = 'notif.add_user_profil';
    public const UPDATE_USER_PROFIL = 'notif.update_user_profil';
    public const DELETE_USER_PROFIL = 'notif.delete_user_profil';


    public function __construct(public UserProfil $userProfil)
    {
    }
}
