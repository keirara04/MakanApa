<?php

namespace App\Support;

/** Reactions on a community post — one per user per post (see community_post_reactions). */
enum CommunityReaction: string
{
    case Up = 'up';       // 👍
    case Fire = 'fire';   // 🔥
    case Drool = 'drool'; // 🤤
}
