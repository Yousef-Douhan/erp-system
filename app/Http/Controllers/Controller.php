<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * المنظمة الحالية: منظمة المستخدم المسجل، وإلا أول منظمة في النظام
     */
    protected function currentOrganizationId(): int
    {
        $orgId = \App\Support\Tenancy::orgId() ?? \App\Models\Organization::value('id');

        if (!$orgId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'organization_id' => 'يجب إنشاء منظمة أولاً من شاشة المنظمات.',
            ]);
        }

        return (int) $orgId;
    }
}
