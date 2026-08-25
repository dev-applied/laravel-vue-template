<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What Single Logout needs and a sign-in does not.
 *
 * An SP-initiated LogoutRequest has to name WHO is being logged out, in the
 * IdP's own terms — the NameID exactly as it arrived, in its original Format,
 * plus the SessionIndex identifying the session to end. None of that is
 * derivable later: `provider_id` is usually the NameID but a project can point
 * SAML_ATTR_SUBJECT at any attribute, and the moment it does, the two diverge.
 *
 * All nullable. Only the `saml` provider ever fills them, an OIDC identity
 * never does, and a SAML identity created before this migration simply has no
 * session to end.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_sso_identities') || Schema::hasColumn('user_sso_identities', 'name_id')) {
            return;
        }

        Schema::table('user_sso_identities', function (Blueprint $table) {
            // Indexed: an incoming LogoutRequest identifies the subject ONLY by
            // NameID, so this is the lookup on every IdP-initiated logout.
            $table->string('name_id')->nullable()->index();
            $table->string('name_id_format')->nullable();
            $table->string('session_index')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_sso_identities')) {
            return;
        }

        Schema::table('user_sso_identities', function (Blueprint $table) {
            $table->dropIndex(['name_id']);
            $table->dropColumn(['name_id', 'name_id_format', 'session_index']);
        });
    }
};
