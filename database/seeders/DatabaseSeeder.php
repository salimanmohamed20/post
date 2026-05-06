<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مدير الموقع',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'editor@example.com'],
            [
                'name' => 'محرر المحتوى',
                'password' => Hash::make('password'),
                'role' => 'editor',
                'email_verified_at' => now(),
            ],
        );

        $categories = collect([
            ['name' => 'أخبار التقنية', 'slug' => 'akhbar-alteqnia'],
            ['name' => 'ريادة الأعمال', 'slug' => 'reyadat-alaamal'],
            ['name' => 'التسويق الرقمي', 'slug' => 'altasweeq-alraqmi'],
            ['name' => 'تجارب المستخدم', 'slug' => 'tajareb-almustakhdem'],
            ['name' => 'إدارة المحتوى', 'slug' => 'edarat-almohtawa'],
        ])->mapWithKeys(fn (array $category): array => [
            $category['slug'] => Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name']],
            ),
        ]);

        Page::query()->updateOrCreate(
            ['slug' => 'about-us'],
            [
                'title' => 'من نحن',
                'body' => '<p>هذه صفحة تعريفية تجريبية للموقع تعرض فكرة المشروع وطريقة إدارة المحتوى.</p>',
                'meta_title' => 'من نحن',
                'meta_description' => 'تعرف على فريق الموقع ورؤية المحتوى.',
            ],
        );

        Page::query()->updateOrCreate(
            ['slug' => 'contact'],
            [
                'title' => 'اتصل بنا',
                'body' => '<p>يمكنك استخدام هذه الصفحة كنموذج أولي لمعلومات التواصل أو بيانات الشركة.</p>',
                'meta_title' => 'اتصل بنا',
                'meta_description' => 'طرق التواصل مع إدارة الموقع.',
            ],
        );

        $titles = [
            'كيف تبني خطة محتوى ناجحة',
            'أفضل أدوات متابعة الأداء',
            'دليل مبسط لتحسين تجربة المستخدم',
            'أفكار عملية لزيادة التفاعل',
            'خطوات تنظيم فريق التحرير',
            'ما الجديد في عالم التقنية',
            'مبادئ كتابة مقال واضح',
            'كيف تختار تصنيف المقال',
            'أهمية السيو في الصفحات الثابتة',
            'نصائح لإدارة صور المقالات',
            'قراءة في اتجاهات التسويق',
            'كيف تراجع المقال قبل النشر',
            'بناء أرشيف محتوى منظم',
            'أساسيات العمل على لوحة Filament',
            'لماذا تحتاج إلى تقويم نشر',
            'تحسين سرعة قراءة المقالات',
            'أمثلة على عناوين جذابة',
            'كيف تجهز محتوى قابل للاستيراد',
            'تنظيف البيانات قبل الإطلاق',
            'طريقة قياس نجاح المقالات',
            'إدارة المستخدمين والصلاحيات',
            'ملاحظات حول الصور المتعددة',
            'اختبار روابط المقالات',
            'خريطة الموقع وأهميتها',
        ];

        foreach ($titles as $index => $title) {
            $category = $categories->values()[$index % $categories->count()];
            $isPublished = $index < 18;

            Article::query()->updateOrCreate(
                ['slug' => 'demo-article-' . ($index + 1)],
                [
                    'title' => $title,
                    'body' => '<p>هذا محتوى تجريبي طويل نسبياً للمقال. يمكنك تعديله من لوحة التحكم واستبداله بالمحتوى الحقيقي لاحقاً.</p><p>الهدف من هذه البيانات هو اختبار القوائم والتصنيفات والبحث والتصفية وحالة النشر.</p>',
                    'excerpt' => 'مقتطف تجريبي قصير يساعد على عرض بطاقة المقال بشكل واضح في الواجهة العامة ولوحة التحكم.',
                    'category_id' => $category->id,
                    'published_at' => $isPublished ? Carbon::now()->subDays($index + 1) : null,
                    'is_published' => $isPublished,
                ],
            );
        }
    }
}
