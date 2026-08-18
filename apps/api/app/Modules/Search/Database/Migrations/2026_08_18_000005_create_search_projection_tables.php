<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_projection_documents', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->string('document_type', 64);
            $table->string('document_id', 128);
            $table->string('title', 255);
            $table->text('searchable_text');
            $table->json('payload');
            $table->unsignedBigInteger('source_version');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->primary(['tenant_id', 'document_type', 'document_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'document_type', 'updated_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE search_projection_documents ENABLE ROW LEVEL SECURITY;
                ALTER TABLE search_projection_documents FORCE ROW LEVEL SECURITY;
                CREATE POLICY search_projection_documents_tenant_isolation ON search_projection_documents
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_projection_documents');
    }
};
