<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\ContactPageContent;
use App\Models\FaqContent;
use App\Models\FaqGroup;
use App\Models\FaqQuestion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_contact_content_management(): void
    {
        $this
            ->getJson(
                '/api/admin/contact-content'
            )
            ->assertUnauthorized();

        $this
            ->putJson(
                '/api/admin/contact-content',
                []
            )
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_faq_management(): void
    {
        $this
            ->getJson(
                '/api/admin/faqs'
            )
            ->assertUnauthorized();

        $this
            ->putJson(
                '/api/admin/faqs',
                []
            )
            ->assertUnauthorized();
    }

    public function test_admin_without_cms_permission_cannot_access_cms_content(): void
    {
        $admin =
            $this->createAdmin(
                false
            );

        Sanctum::actingAs(
            $admin
        );

        $this
            ->getJson(
                '/api/admin/contact-content'
            )
            ->assertForbidden();

        $this
            ->getJson(
                '/api/admin/faqs'
            )
            ->assertForbidden();
    }

    public function test_admin_with_cms_permission_can_view_contact_and_faq_content(): void
    {
        $admin =
            $this->createAdmin(
                true
            );

        $this->createContactContent();

        $faq =
            $this->createFaqContent();

        $group =
            $this->createFaqGroup(
                $faq
            );

        $this->createFaqQuestion(
            $group
        );

        Sanctum::actingAs(
            $admin
        );

        $this
            ->getJson(
                '/api/admin/contact-content'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.content_key',
                ContactPageContent::KEY
            )
            ->assertJsonPath(
                'data.title_en',
                'Stay in touch'
            );

        $this
            ->getJson(
                '/api/admin/faqs'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.content_key',
                FaqContent::KEY
            )
            ->assertJsonPath(
                'data.groups.0.questions.0.question_en',
                'How can I place an order?'
            );
    }

    public function test_admin_can_update_contact_content(): void
    {
        $admin =
            $this->createAdmin(
                true
            );

        $this->createContactContent();

        Sanctum::actingAs(
            $admin
        );

        $response =
            $this->putJson(
                '/api/admin/contact-content',
                [
                    'eyebrow' =>
                        'Contact FA',

                    'eyebrow_en' =>
                        'Contact us',

                    'title' =>
                        'Stay in touch FA',

                    'title_en' =>
                        'Talk to our team',

                    'instagram_label' =>
                        'Instagram FA',

                    'instagram_label_en' =>
                        'Instagram',

                    'instagram_value' =>
                        '@tkb',

                    'instagram_url' =>
                        'https://instagram.com/tkb',

                    'telegram_label' =>
                        'Telegram FA',

                    'telegram_label_en' =>
                        'Telegram',

                    'telegram_value' =>
                        '09120000000',

                    'telegram_url' =>
                        'tg://resolve?phone=+989120000000',

                    'whatsapp_label' =>
                        'WhatsApp FA',

                    'whatsapp_label_en' =>
                        'WhatsApp',

                    'whatsapp_value' =>
                        '09120000000',

                    'whatsapp_url' =>
                        'https://wa.me/989120000000',

                    'phone_label' =>
                        'Phone FA',

                    'phone_label_en' =>
                        'Phone',

                    'phone_value' =>
                        '02100000000',

                    'phone_url' =>
                        'tel:02100000000',

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title_en',
                'Talk to our team'
            )
            ->assertJsonPath(
                'data.instagram_value',
                '@tkb'
            )
            ->assertJsonPath(
                'data.phone_value',
                '02100000000'
            );

        $this->assertDatabaseHas(
            'contact_page_contents',
            [
                'content_key' =>
                    ContactPageContent::KEY,

                'title_en' =>
                    'Talk to our team',

                'instagram_value' =>
                    '@tkb',

                'phone_value' =>
                    '02100000000',
            ]
        );
    }

    public function test_admin_can_replace_faq_structure_atomically(): void
    {
        $admin =
            $this->createAdmin(
                true
            );

        $faq =
            $this->createFaqContent();

        $oldGroup =
            $this->createFaqGroup(
                $faq
            );

        $oldQuestion =
            $this->createFaqQuestion(
                $oldGroup
            );

        Sanctum::actingAs(
            $admin
        );

        $response =
            $this->putJson(
                '/api/admin/faqs',
                [
                    'title' =>
                        'FAQ FA',

                    'title_en' =>
                        'Customer Questions',

                    'intro' =>
                        'Intro FA',

                    'intro_en' =>
                        'Everything you need to know.',

                    'is_active' =>
                        true,

                    'groups' => [
                        [
                            'id' =>
                                $oldGroup->id,

                            'title' =>
                                'Orders FA',

                            'title_en' =>
                                'Orders',

                            'sort_order' =>
                                2,

                            'is_active' =>
                                true,

                            'questions' => [
                                [
                                    'id' =>
                                        $oldQuestion->id,

                                    'question' =>
                                        'Updated FA',

                                    'question_en' =>
                                        'How do I order?',

                                    'answer' =>
                                        'Updated answer FA',

                                    'answer_en' =>
                                        'Choose a product and complete checkout.',

                                    'sort_order' =>
                                        2,

                                    'is_active' =>
                                        true,
                                ],
                            ],
                        ],

                        [
                            'title' =>
                                'Delivery FA',

                            'title_en' =>
                                'Delivery',

                            'sort_order' =>
                                1,

                            'is_active' =>
                                true,

                            'questions' => [
                                [
                                    'question' =>
                                        'Delivery FA',

                                    'question_en' =>
                                        'How long does delivery take?',

                                    'answer' =>
                                        'Delivery answer FA',

                                    'answer_en' =>
                                        'Delivery time depends on destination.',

                                    'sort_order' =>
                                        1,

                                    'is_active' =>
                                        true,
                                ],
                            ],
                        ],
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title_en',
                'Customer Questions'
            )
            ->assertJsonCount(
                2,
                'data.groups'
            );

        $this->assertDatabaseHas(
            'faq_questions',
            [
                'id' =>
                    $oldQuestion->id,

                'question_en' =>
                    'How do I order?',

                'sort_order' =>
                    2,
            ]
        );

        $this->assertDatabaseHas(
            'faq_groups',
            [
                'faq_content_id' =>
                    $faq->id,

                'title_en' =>
                    'Delivery',

                'sort_order' =>
                    1,
            ]
        );

        $this->assertDatabaseHas(
            'faq_questions',
            [
                'question_en' =>
                    'How long does delivery take?',

                'sort_order' =>
                    1,
            ]
        );

        $this->assertSame(
            2,
            FaqGroup::query()
                ->where(
                    'faq_content_id',
                    $faq->id
                )
                ->count()
        );

        $this->assertSame(
            2,
            FaqQuestion::query()
                ->count()
        );
    }

    public function test_faq_update_removes_omitted_questions(): void
    {
        $admin =
            $this->createAdmin(
                true
            );

        $faq =
            $this->createFaqContent();

        $group =
            $this->createFaqGroup(
                $faq
            );

        $keep =
            $this->createFaqQuestion(
                $group,
                [
                    'question_en' =>
                        'Keep me',
                ]
            );

        $remove =
            $this->createFaqQuestion(
                $group,
                [
                    'question' =>
                        'Remove FA',

                    'question_en' =>
                        'Remove me',

                    'sort_order' =>
                        2,
                ]
            );

        Sanctum::actingAs(
            $admin
        );

        $this
            ->putJson(
                '/api/admin/faqs',
                [
                    'title' =>
                        $faq->title,

                    'title_en' =>
                        $faq->title_en,

                    'intro' =>
                        null,

                    'intro_en' =>
                        null,

                    'is_active' =>
                        true,

                    'groups' => [
                        [
                            'id' =>
                                $group->id,

                            'title' =>
                                $group->title,

                            'title_en' =>
                                $group->title_en,

                            'sort_order' =>
                                1,

                            'is_active' =>
                                true,

                            'questions' => [
                                [
                                    'id' =>
                                        $keep->id,

                                    'question' =>
                                        $keep->question,

                                    'question_en' =>
                                        $keep->question_en,

                                    'answer' =>
                                        $keep->answer,

                                    'answer_en' =>
                                        $keep->answer_en,

                                    'sort_order' =>
                                        1,

                                    'is_active' =>
                                        true,
                                ],
                            ],
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'faq_questions',
            [
                'id' =>
                    $keep->id,
            ]
        );

        $this->assertDatabaseMissing(
            'faq_questions',
            [
                'id' =>
                    $remove->id,
            ]
        );
    }

    private function createAdmin(
        bool $withCmsPermission
    ): User {
        $role =
            Role::create([
                'name' =>
                    'Admin',

                'slug' =>
                    'admin',
            ]);

        if (
            $withCmsPermission
        ) {
            $permission =
                Permission::create([
                    'name' =>
                        'Manage CMS',

                    'slug' =>
                        'cms.manage',
                ]);

            $role
                ->permissions()
                ->sync([
                    $permission->id,
                ]);
        }

        $user =
            User::factory()->create([
                'status' =>
                    UserStatus::ACTIVE,
            ]);

        $user
            ->roles()
            ->attach(
                $role->id
            );

        return $user;
    }

    private function createContactContent(): ContactPageContent
    {
        return ContactPageContent::create([
            'content_key' =>
                ContactPageContent::KEY,

            'eyebrow' =>
                'Contact FA',

            'eyebrow_en' =>
                'Contact us',

            'title' =>
                'Stay in touch FA',

            'title_en' =>
                'Stay in touch',

            'instagram_label' =>
                'Instagram FA',

            'instagram_label_en' =>
                'Instagram',

            'instagram_value' =>
                'https://instagram.com/',

            'instagram_url' =>
                'https://instagram.com/',

            'telegram_label' =>
                'Telegram FA',

            'telegram_label_en' =>
                'Telegram',

            'telegram_value' =>
                '09121111111',

            'telegram_url' =>
                'tg://resolve?phone=+989121111111',

            'whatsapp_label' =>
                'WhatsApp FA',

            'whatsapp_label_en' =>
                'WhatsApp',

            'whatsapp_value' =>
                '09121111111',

            'whatsapp_url' =>
                'https://wa.me/989121111111',

            'phone_label' =>
                'Phone FA',

            'phone_label_en' =>
                'Phone',

            'phone_value' =>
                '09121111111',

            'phone_url' =>
                'tel:09121111111',

            'is_active' =>
                true,
        ]);
    }

    private function createFaqContent(): FaqContent
    {
        return FaqContent::create([
            'content_key' =>
                FaqContent::KEY,

            'title' =>
                'FAQ FA',

            'title_en' =>
                'Frequently Asked Questions',

            'intro' =>
                null,

            'intro_en' =>
                null,

            'is_active' =>
                true,
        ]);
    }

    private function createFaqGroup(
        FaqContent $faq
    ): FaqGroup {
        return $faq
            ->groups()
            ->create([
                'title' =>
                    'General FA',

                'title_en' =>
                    'General',

                'sort_order' =>
                    1,

                'is_active' =>
                    true,
            ]);
    }

    private function createFaqQuestion(
        FaqGroup $group,
        array $attributes = []
    ): FaqQuestion {
        return $group
            ->questions()
            ->create(
                array_merge(
                    [
                        'question' =>
                            'Order FA',

                        'question_en' =>
                            'How can I place an order?',

                        'answer' =>
                            'Answer FA',

                        'answer_en' =>
                            'Choose a product and complete checkout.',

                        'sort_order' =>
                            1,

                        'is_active' =>
                            true,
                    ],
                    $attributes
                )
            );
    }
}