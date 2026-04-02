<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sites = DB::table('sites')->get();

        foreach ($sites as $site) {
            // Original primary domain
            if (isset($site->primary_domain) && filled($site->primary_domain)) {
                DB::table('domains')->updateOrInsert(
                    ['server_id' => $site->server_id, 'name' => $site->primary_domain],
                    [
                        'site_id' => $site->id,
                        'type' => 'primary',
                        'php_version' => $site->php_version ?? null,
                        'web_root' => $site->web_root ?? 'public',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // Subdomains logic
            if (isset($site->subdomains) && filled($site->subdomains)) {
                $subdomains = json_decode($site->subdomains, true);
                if (is_array($subdomains)) {
                    foreach ($subdomains as $subdomain) {
                        DB::table('domains')->updateOrInsert(
                            ['server_id' => $site->server_id, 'name' => $subdomain],
                            [
                                'site_id' => $site->id,
                                'type' => 'subdomain',
                                'php_version' => $site->php_version ?? null,
                                'web_root' => $site->web_root ?? 'public',
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }

            // Alias domains logic
            if (isset($site->alias_domains) && filled($site->alias_domains)) {
                $aliases = json_decode($site->alias_domains, true);
                if (is_array($aliases)) {
                    foreach ($aliases as $alias) {
                        DB::table('domains')->updateOrInsert(
                            ['server_id' => $site->server_id, 'name' => $alias],
                            [
                                'site_id' => $site->id,
                                'type' => 'alias',
                                'php_version' => $site->php_version ?? null,
                                'web_root' => $site->web_root ?? 'public',
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('domains')->truncate();
    }
};
