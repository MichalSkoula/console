<?php

class ModuleArticle extends Module
{
    protected string $name = 'article';

    public function requirements(mixed $we): void
    {
        $we->needTable(function ($table): void {
            $table->primary('id');
            $table->string('title');
            $table->string('slug')
                ->unique();
            $table->string('content');
            $table->timestamps();
        });

        $we->wantPageList(function ($page): void {
            $page->setTitle('List Artikel');
            $page->showSearchField(['title', 'content']);
            $page->showPagination(5, 15);
            $page->showCreateButton();
            $page->showEditButton();
            $page->showDeleteButton();
        })
            ->forRoles(['admin', 'developer']);

        $we->wantFormCreate(function ($form): void {
            $form->setTitle('Buat Artikel Baru');

            $form->showTextField('title', 'Judul')
                ->withRules('required');

            $form->showSelectOption('id_category', 'Kategori')
                ->withOptionsFromTable('category', 'id', 'name');

            $form->showTextField('slug', 'Slug')
                ->withRules('required|unique');

            $form->showRichTextarea('name', 'Konten')
                ->withRules('required');
        })
            ->forRoles(['admin', 'developer']);

        $we->wantFormEdit(function ($form): void {
            $form->setTitle('Buat Artikel Baru');

            $form->showTextField('title', 'Judul')
                ->withRules('required');

            $form->showSelectOption('id_category', 'Kategori')
                ->withOptionsFromTable('category', 'id', 'name');

            $form->showTextField('slug', 'Slug')
                ->withRules('required|unique');

            $form->showRichTextarea('name', 'Konten')
                ->withRules('required');
        })
            ->forRoles(['admin', 'developer']);

        $we->wantFunctionDelete()
            ->forRoles(['admin', 'developer']);
    }
}
