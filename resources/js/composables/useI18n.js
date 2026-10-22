import { computed, ref } from 'vue';

const STORAGE_KEY = 'locale';
export const LOCALE_AR = 'ar';
export const LOCALE_EN = 'en';

const locale = ref(readLocale());

function readLocale() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === LOCALE_AR || stored === LOCALE_EN) return stored;
    } catch {
        // Private browsing or storage blocked
    }
    return LOCALE_AR;
}

function applyLocale(loc) {
    if (typeof document === 'undefined') return;
    document.documentElement.lang = loc;
    document.documentElement.dir = loc === LOCALE_AR ? 'rtl' : 'ltr';
}

// Initial application
applyLocale(locale.value);

export const messages = {
    ar: {
        langName: 'العربية',
        otherLangName: 'English',
        switchLangLabel: 'التبديل إلى الإنجليزية',
        brand: {
            name: 'نظام الهواتف والإكسسوارات',
            company: 'داماتك لتقنية المعلومات',
            tagline: 'نظام متكامل لمحلات الهواتف ومراكز الصيانة',
        },
        nav: {
            features: 'المزايا',
            workflow: 'مسار الصيانة',
            modules: 'الأقسام',
            whyUs: 'لماذا نحن',
            faq: 'الأسئلة الشائعة',
            login: 'تسجيل الدخول',
            register: 'أنشئ متجرك مجاناً',
            quickTrial: 'جرّب مجاناً',
            openMenu: 'فتح القائمة',
            closeMenu: 'إغلاق القائمة',
        },
        theme: {
            toLight: 'التبديل إلى الوضع الفاتح',
            toDark: 'التبديل إلى الوضع الداكن',
        },
        hero: {
            badge: 'نظام سحابي متكامل لمحلات الهواتف ومراكز الصيانة',
            titlePrefix: 'أدِر محلّك كاملاً',
            titleHighlight: 'من الكاونتر حتى التقارير',
            description:
                'المبيعات والمشتريات، استلام أجهزة الصيانة وتتبّعها، المخزون والصندوق وحسابات الخبراء — في واجهة عصرية وسريعة تعمل بسلاسة على الحاسوب واللوحي والجوال.',
            ctaPrimary: 'أنشئ متجرك مجاناً',
            ctaLogin: 'الدخول إلى النظام',
            ctaHaveShop: 'لديّ متجر — تسجيل الدخول',
            ctaRequestCopy: 'اطلب نسخة لمحلّك',
            ctaExplore: 'استعرض المزايا',
            pointsWithTrial: [
                'تجربة مجانية {days} يوماً',
                'بدون بطاقة دفع',
                'متجرك جاهز خلال دقيقة واحدة',
            ],
            pointsStandard: [
                'يعمل من المتصفح ويُثبَّت كتطبيق',
                'طباعة إيصالات وفواتير حرارية وA4',
                'صلاحيات دقيقة لكل موظف',
            ],
        },
        mockup: {
            windowUrl: 'repair.damatech.sy',
            ticketNo: '#1042',
            deviceModel: 'iPhone 13 Pro — أزرق',
            issueLabel: 'العطل:',
            issueDesc: 'الشاشة لا تستجيب للّمس وتحتاج فحص',
            accessoryCase: 'مع الغطاء',
            accessoryNoCharger: 'بدون شاحن',
            imei: 'IMEI 3568…5012',
            timelinePrompt: 'مسار الجهاز — اضغط على أي مرحلة لمعاينتها',
            activeRepairsLabel: 'أجهزة قيد الصيانة',
            readyForPickupLabel: '6 جاهزة للتسليم',
            todaySalesLabel: 'مبيعات اليوم',
            invoicesCountLabel: '27 فاتورة',
            boardTitle: 'لوحة الصيانة',
            notifyTitle: 'واتساب · الآن',
            notifyText: 'جهازك iPhone 13 Pro جاهز للتسليم ✓',
            steps: [
                { short: 'استلام', label: 'مستلَم' },
                { short: 'فحص', label: 'قيد الفحص' },
                { short: 'إصلاح', label: 'قيد الإصلاح' },
                { short: 'جاهز', label: 'جاهز للتسليم' },
            ],
        },
        modulesSection: {
            title: 'أقسام النظام المتكاملة',
            list: [
                { icon: '🧾', label: 'المبيعات' },
                { icon: '📦', label: 'المشتريات' },
                { icon: '🔧', label: 'الصيانة' },
                { icon: '🏷️', label: 'المخزون والمواد' },
                { icon: '💵', label: 'الصندوق والدفعات' },
                { icon: '📉', label: 'المصاريف' },
                { icon: '👥', label: 'العملاء والموردون' },
                { icon: '📈', label: 'التقارير' },
            ],
        },
        featuresSection: {
            eyebrow: 'المزايا الشاملة',
            title: 'كل ما يحتاجه متجرك، في شاشة واحدة منظمة',
            text: 'صُمّم النظام وفق بيئة العمل الحقيقية لمتاجر الهواتف: زبون على الكاونتر، جهاز للصيانة، فواتير شراء سريعة، وصندوق دقيق يتطابق تلقائياً.',
            items: [
                {
                    icon: '🔧',
                    tone: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
                    wide: true,
                    title: 'استلام أجهزة الصيانة وتتبّعها بدقة',
                    text: 'سجّل الجهاز والعطل والملحقات المستلمة والسيريال/IMEI خلال ثوانٍ، واطبع إيصال الاستلام للزبون، ثم أرسل له إشعار واتساب بضغطة زر واحدة عند كل مرحلة.',
                    points: ['مستلَم', 'قيد الفحص', 'قيد الإصلاح', 'بانتظار موافقة العميل', 'جاهز للتسليم', 'تم التسليم'],
                },
                {
                    icon: '🧑‍🔧',
                    tone: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                    title: 'حسابات خبراء الصيانة والعمولات',
                    text: 'حدّد نسبة كل فني أو خبير من قيمة الإصلاح، وتابع مستحقاته ورصيده وأرباحه في كشف حساب واضح وشفاف.',
                },
                {
                    icon: '🧾',
                    tone: 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400',
                    title: 'فواتير بيع وشراء بنقرة واحدة',
                    text: 'أضف المواد بالبحث الفوري أو بقارئ الباركود، واحفظ الفاتورة نقداً أو آجلاً على حساب العميل أو المورّد بكل مرونة.',
                },
                {
                    icon: '🏷️',
                    tone: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                    wide: true,
                    title: 'إدارة المخزون وتكلفة المواد',
                    text: 'كل حركة بيع أو صيانة أو شراء تُحدّث المخزون فورياً بطرق احتساب التكلفة الحديثة، مع تنبيهات المواد الناقصة وتقارير حركة المخزون والأرباح.',
                    points: ['تقرير الأرباح', 'حركة المخزون', 'المواد الناقصة', 'أرصدة الحسابات', 'كشف حساب تفصيلي'],
                },
                {
                    icon: '💱',
                    tone: 'border-sky-500/30 bg-sky-500/10 text-sky-600 dark:text-sky-400',
                    wide: true,
                    title: 'صندوق ودفعات بعدّة عملات وسعر صرف',
                    text: 'سجّل المقبوضات والمدفوعات والمصاريف بالعملات المحلية والأجنبية مع احتساب تلقائي بالعملة الأساسية للحفاظ على مطابقة الصندوق دائماً.',
                },
                {
                    icon: '🔐',
                    tone: 'border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400',
                    title: 'أدوار وصلاحيات أمان صارمة',
                    text: 'تحكم بما يراه كل موظف: موظف الكاونتر، خبير الصيانة، المحاسب، أو المدير العام، مع حماية أمنية للبيانات الحساسة.',
                },
            ],
        },
        workflowSection: {
            eyebrow: 'مسار الصيانة الذكي',
            title: 'من استلام الجهاز حتى تسليمه، في 4 خطوات سلسة',
            text: 'لكل جهاز سجل رقمي دقيق: مَن استلمه، نوع العطل، قطع الغيار المستهلكة، الفني المسؤول، وتفاصيل الدفع.',
            steps: [
                {
                    number: '1',
                    title: 'الاستلام والتوثيق',
                    text: 'تدوين بيانات الزبون، العطل، حالة الجهاز والملحقات وطباعة إيصال الاستلام برمز الاستجابة أو الباركود.',
                },
                {
                    number: '2',
                    title: 'الفحص وتقدير التكلفة',
                    text: 'إسناد الجهاز للفني المختص، تشخيص العطل بدقة، وتحديد التكلفة التقديرية وقطع الغيار المطلوبة.',
                },
                {
                    number: '3',
                    title: 'إشعار الزبون الفوري',
                    text: 'تجهيز رسالة واتساب بنقرة واحدة لإبلاغ الزبون بالموافقة أو بجاهزية جهازه دون اتصالات متكررة.',
                },
                {
                    number: '4',
                    title: 'التسليم وتسوية الحساب',
                    text: 'تسليم الجهاز مع اختبار الجودة، تحصيل المبلغ وتسجيله في الصندوق، واحتساب نسبة الفني تلقائياً.',
                },
            ],
        },
        whyUsSection: {
            eyebrow: 'لماذا داماتك ريبير',
            title: 'مبني للعمل الفعلي، لأعلى سرعة وأقل جهد',
            items: [
                {
                    icon: '⚡',
                    title: 'سريع جداً على الكاونتر',
                    text: 'واجهة مختصرة مدروسة تقلل النقرات وتسمح بإنجاز الفواتير وأوامر الصيانة في ثوانٍ معدودة.',
                },
                {
                    icon: '📱',
                    title: 'يعمل على أي جهاز وشاشة',
                    text: 'تصميم متجاوب بالكامل يدعم الحاسوب واللابتوب والتابلت والهاتف، مع إمكانية التثبيت كتطبيق PWA.',
                },
                {
                    icon: '🖨️',
                    title: 'طباعة فورية لكافة القياسات',
                    text: 'دعم كامل لطابعات الإيصالات الحرارية (80mm / 58mm) وطابعات A4 وA5 مع إمكانية تخصيص الترويسة والشعار.',
                },
                {
                    flag: true,
                    title: 'عربي بالكامل وتوافق محلي',
                    text: 'واجهة عربية أصيلة صُممت خصيصاً لاحتياجات السوق مع دعم فني سريع ومباشر.',
                },
            ],
        },
        faqSection: {
            eyebrow: 'الأسئلة الشائعة',
            title: 'كل ما تحتاج لمعرفته قبل البدء',
            trialFaq: {
                q: 'كيف أبدأ؟ وماذا يحدث بعد انتهاء الفترة التجريبية؟',
                a: 'اضغط على زر "أنشئ متجرك مجاناً" واملأ بيانات متجرك لتحصل فوراً على عنوان مستقل وقاعدة بيانات خاصة بك جاهزة للعمل خلال دقيقة. التجربة مجانية بالكامل بكافة الخصائص لمدة {days} يوماً، وتستطيع التواصل معنا لتفعيل اشتراكك دون فقدان أي بيانات.',
            },
            items: [
                {
                    q: 'هل أحتاج لتثبيت برنامج أو خادم خاص في المحل؟',
                    a: 'لا على الإطلاق. النظام سحابي بالكامل يعمل مباشرة عبر المتصفح من أي مكان، ويمكن تثبيته كأيقونة تطبيق سريعة على حاسوبك أو هاتفك بنقرة واحدة.',
                },
                {
                    q: 'هل يمكن لأكثر من موظف العمل معاً في نفس اللحظة؟',
                    a: 'نعم بالتأكيد! يمكنك إنشاء حسابات لكافة الموظفين والفنيين وتعيين صلاحيات مخصصة لكل منهم، وتتحدث البيانات لحظياً بين كافة الأجهزة.',
                },
                {
                    q: 'هل يدعم النظام طابعات الفواتير والإيصالات الحرارية؟',
                    a: 'نعم، يدعم طابعات البلوتوث والـ USB والشبكة بمقاسات 80mm و58mm بالإضافة إلى طابعات الورق المكتبي A4 وA5، مع إمكانية حفظ إعدادات الطباعة لكل جهاز على حدة.',
                },
                {
                    q: 'كيف يتم التواصل مع الزبائن وإشعارهم؟',
                    a: 'يوفر النظام قوالب رسائل واتساب ذكية ومجهزة مسبقاً لكل مرحلة من مراحل الصيانة (الاستلام، الموافقة على السعر، الجاهزية للتسليم) تُرسل للزبون بضغطة زر.',
                },
                {
                    q: 'هل بيانات متجري ومبيعاتي آمنة ومحمية؟',
                    a: 'بياناتك مشفرة ومحفوظة على خوادم سحابية مؤمنة بنسخ احتياطي دوري، ولكل متجر قاعدة بيانات مستقلة تماماً لضمان الخصوصية التامة.',
                },
            ],
        },
        ctaSection: {
            title: 'جاهز لتنظيم وإدارة محلّك باحترافية؟',
            subtitleTrial: 'أنشئ متجرك الآن وجرّب النظام بكامل ميزاته مجاناً لمدة {days} يوماً.',
            subtitleStandard: 'سجّل الدخول بحسابك وابدأ إدارة المبيعات وأجهزة الصيانة والصندوق بكل سهولة.',
            buttonTrial: 'ابدأ تجربتك المجانية الآن',
            buttonLogin: 'الدخول إلى النظام',
            buttonContact: 'تواصل معنا عبر واتساب',
        },
        factsSection: {
            items: [
                { value: '6', label: 'مراحل لتتبّع كل جهاز' },
                { value: '80 · 58 · A4', label: 'كل مقاسات الطباعة' },
                { value: '1', unit: 'دقيقة', label: 'أو أقل لتجهيز متجر جديد' },
                { value: 'AR · EN', label: 'واجهة عربية وإنجليزية' },
            ],
        },
        footer: {
            product: 'النظام',
            account: 'الحساب',
            copyright: 'جميع الحقوق محفوظة.',
            tagline: 'حلول برمجية متطورة لإدارة الصيانة ونقاط البيع.',
        },
        shopFinder: {
            brandName: 'نظام الهواتف والإكسسوارات',
            title: 'الدخول إلى متجرك',
            subtitle: 'لكل متجر عنوان فرعي خاص به. اكتب اسم متجرك كما في العنوان الذي استلمته.',
            label: 'عنوان المتجر',
            placeholder: 'myshop',
            submit: 'متابعة إلى المتجر',
            noShop: 'لا تملك متجراً بعد؟',
            createShop: 'أنشئ متجرك مجاناً',
            learnMore: 'تعرّف على مزايا النظام',
            errorFormat: 'اكتب اسم المتجر بالأحرف الإنجليزية والأرقام والشرطة فقط، مثل: alnour',
            backHome: 'العودة للرئيسية',
        },
        register: {
            brandName: 'نظام الهواتف والإكسسوارات',
            title: 'أنشئ متجرك مجاناً',
            subtitle: 'تجربة مجانية كاملة لمدة {days} يوماً، بدون أي بطاقة دفع. يحصل متجرك على عنوان خاص وقاعدة بيانات مستقلة وفورية.',
            shopSection: 'معلومات المتجر',
            shopNameLabel: 'اسم المتجر',
            shopNamePlaceholder: 'مثال: مركز النور للهواتف والصيانة',
            slugLabel: 'عنوان المتجر الإلكتروني',
            slugPlaceholder: 'alnour',
            slugHint: 'أحرف إنجليزية صغيرة وأرقام وشرطة (-). هذا هو العنوان الفرعي الذي ستدخل منه دائماً.',
            slugChecking: 'جارٍ التحقق من التوفر...',
            slugAvailable: 'العنوان متاح!',
            slugTaken: 'هذا العنوان محجوز، اختر عنواناً آخر.',
            ownerSection: 'حساب المدير المسؤول',
            ownerNameLabel: 'الاسم الكامل',
            ownerPhoneLabel: 'رقم الجوال للتواصل',
            ownerEmailLabel: 'البريد الإلكتروني (لتسجيل الدخول)',
            passwordLabel: 'كلمة المرور',
            passwordConfirmLabel: 'تأكيد كلمة المرور',
            passwordHint: '8 أحرف على الأقل، ويفضل أن تجمع بين حروف وأرقام.',
            showPassword: 'إظهار',
            hidePassword: 'إخفاء',
            termsAgreement: 'بإنشاء المتجر أنت توافق على شروط الخدمة وسياسة الخصوصية.',
            submitButton: 'إنشاء المتجر وبدء التجربة المجانية',
            submittingButton: 'جارٍ تجهيز متجرك وقاعدتك...',
            alreadyHaveShop: 'لديك متجر بالفعل؟',
            loginLink: 'تسجيل الدخول لمتجرك',
            successTitle: 'متجرك جاهز بنجاح!',
            successSubtitle: 'تم إنشاء متجرك بنجاح. فترتك التجريبية المجانية مفعلة حتى {date}.',
            savedAddressNotice: 'عنوان متجرك المخصص — احفظ هذا الرابط للدخول دائماً:',
            loginCredentialsNotice: 'تسجيل الدخول ببريدك الإلكتروني وكلمة المرور التي حددتها الآن.',
            sslWaitNotice: 'جارٍ تجهيز شهادة الأمان لعنوانك الجديد، وقد تستغرق دقيقة أو دقيقتين. إن لم تفتح الصفحة فوراً أعد المحاولة بعد قليل.',
            enterShopButton: 'الانتقال إلى متجري الآن',
        },
    },
    en: {
        langName: 'English',
        otherLangName: 'العربية',
        switchLangLabel: 'Switch to Arabic',
        brand: {
            name: 'Damatech Repair Hub',
            company: 'Damatech Information Technology',
            tagline: 'Integrated Management System for Phone Stores & Repair Centers',
        },
        nav: {
            features: 'Features',
            workflow: 'Workflow',
            modules: 'Modules',
            whyUs: 'Why Us',
            faq: 'FAQ',
            login: 'Sign In',
            register: 'Start Free Trial',
            quickTrial: 'Free Trial',
            openMenu: 'Open menu',
            closeMenu: 'Close menu',
        },
        theme: {
            toLight: 'Switch to Light Mode',
            toDark: 'Switch to Dark Mode',
        },
        hero: {
            badge: 'Next-Gen Cloud System for Repair Shops & Retail',
            titlePrefix: 'Run Your Whole Shop',
            titleHighlight: 'From Counter to Financial Reports',
            description:
                'POS sales and purchasing, device repair intake and tracking, smart inventory with FIFO costing, multi-currency cashbox, and technician commission tracking — in a blazing-fast, modern interface.',
            ctaPrimary: 'Start Free Trial',
            ctaLogin: 'Sign In',
            ctaHaveShop: 'I have a shop — Sign In',
            ctaRequestCopy: 'Contact Sales',
            ctaExplore: 'Explore Features',
            pointsWithTrial: [
                'Free {days}-day trial',
                'No credit card required',
                'Ready in under 60 seconds',
            ],
            pointsStandard: [
                'Runs in browser & installs as PWA',
                'Thermal & A4 receipt printing',
                'Granular staff role permissions',
            ],
        },
        mockup: {
            windowUrl: 'repair.damatech.sy',
            ticketNo: '#1042',
            deviceModel: 'iPhone 13 Pro — Sierra Blue',
            issueLabel: 'Fault:',
            issueDesc: 'Touch screen unresponsive, requires diagnostics',
            accessoryCase: 'With protective case',
            accessoryNoCharger: 'No charger',
            imei: 'IMEI 3568…5012',
            timelinePrompt: 'Repair Lifecycle — Click any stage to preview',
            activeRepairsLabel: 'Active Repairs',
            readyForPickupLabel: '6 ready for delivery',
            todaySalesLabel: "Today's Sales",
            invoicesCountLabel: '27 invoices',
            boardTitle: 'Repair board',
            notifyTitle: 'WhatsApp · now',
            notifyText: 'Your iPhone 13 Pro is ready for pickup ✓',
            steps: [
                { short: 'Intake', label: 'Received' },
                { short: 'Diagnosis', label: 'Inspecting' },
                { short: 'Repair', label: 'In Repair' },
                { short: 'Ready', label: 'Ready for Pickup' },
            ],
        },
        modulesSection: {
            title: 'Integrated Core Modules',
            list: [
                { icon: '🧾', label: 'Sales & POS' },
                { icon: '📦', label: 'Purchases' },
                { icon: '🔧', label: 'Maintenance' },
                { icon: '🏷️', label: 'Inventory & Parts' },
                { icon: '💵', label: 'Cash & Vault' },
                { icon: '📉', label: 'Expenses' },
                { icon: '👥', label: 'Clients & Vendors' },
                { icon: '📈', label: 'Analytics & Reports' },
            ],
        },
        featuresSection: {
            eyebrow: 'Powerful Capabilities',
            title: 'Everything Your Shop Needs, Unified in One Platform',
            text: 'Crafted around the real-world workflow of electronics & phone repair shops: customer at the counter, device diagnostic tickets, vendor purchases, and instant cash reconciliation.',
            items: [
                {
                    icon: '🔧',
                    tone: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
                    wide: true,
                    title: 'Device Intake & Comprehensive Tracking',
                    text: 'Record device specs, customer issues, received accessories, and Serial/IMEI in seconds. Print thermal receipts with barcodes and dispatch instant WhatsApp updates at each milestone.',
                    points: ['Received', 'Inspecting', 'In Repair', 'Awaiting Approval', 'Ready', 'Delivered'],
                },
                {
                    icon: '🧑‍🔧',
                    tone: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400',
                    title: 'Technician Commissions & Statements',
                    text: 'Configure custom commission percentages per technician on repair labour, track payouts, and view full transparent ledger statements.',
                },
                {
                    icon: '🧾',
                    tone: 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400',
                    title: 'Fast Point-of-Sale & Invoicing',
                    text: 'Scan barcodes or search items instantly. Process cash or credit sales and purchases with automated stock ledger updates.',
                },
                {
                    icon: '🏷️',
                    tone: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                    wide: true,
                    title: 'Smart Inventory & FIFO Valuation',
                    text: 'Track real-time stock levels, weighted or FIFO cost layers, low-stock reorder warnings, and generate gross profit analytics on demand.',
                    points: ['Profit Margins', 'Stock Movements', 'Low Stock Alerts', 'Ledger Balances', 'Periodic Summaries'],
                },
                {
                    icon: '💱',
                    tone: 'border-sky-500/30 bg-sky-500/10 text-sky-600 dark:text-sky-400',
                    wide: true,
                    title: 'Multi-Currency Cash Drawer & Vault',
                    text: 'Manage payments and daily expenses in multiple currencies with live exchange rates, keeping your cash register balanced down to the cent.',
                },
                {
                    icon: '🔐',
                    tone: 'border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400',
                    title: 'Strict Role-Based Access Control',
                    text: 'Control what each staff member can see and do: front counter staff, technicians, accountants, or shop owners.',
                },
            ],
        },
        workflowSection: {
            eyebrow: 'Streamlined Lifecycle',
            title: 'From Device Intake to Handover in 4 Simple Steps',
            text: 'Every device has an auditable timeline: who received it, fault diagnosis, spare parts used, assigned technician, and payment records.',
            steps: [
                {
                    number: '1',
                    title: 'Intake & Documentation',
                    text: 'Log customer info, fault details, passcode/pattern, and print a professional intake receipt with barcode.',
                },
                {
                    number: '2',
                    title: 'Diagnosis & Estimate',
                    text: 'Assign device to a specialized technician, determine required spare parts, and estimate cost.',
                },
                {
                    number: '3',
                    title: 'One-Click WhatsApp Notice',
                    text: 'Notify the customer immediately via automated WhatsApp messages without time-consuming manual calls.',
                },
                {
                    number: '4',
                    title: 'Quality Check & Delivery',
                    text: 'Test functionality, collect invoice payment into the cash register, and credit the technician share automatically.',
                },
            ],
        },
        whyUsSection: {
            eyebrow: 'Why Damatech',
            title: 'Engineered for Daily Speed and High Reliability',
            items: [
                {
                    icon: '⚡',
                    title: 'Counter-Optimized Speed',
                    text: 'Clean keyboard-friendly interface designed to minimize clicks during busy rush hours.',
                },
                {
                    icon: '📱',
                    title: 'Works on Any Device',
                    text: 'Responsive layout for desktop, laptop, tablet, and smartphone. Installs as an offline-ready PWA.',
                },
                {
                    icon: '🖨️',
                    title: 'Flexible Receipt Printing',
                    text: 'Native support for 80mm & 58mm thermal printers as well as standard A4 and A5 invoice layouts.',
                },
                {
                    flag: true,
                    title: 'Bilingual & Localized',
                    text: 'Bilingual English and Arabic support with full RTL/LTR adaptability and local timezone/currency formatting.',
                },
            ],
        },
        faqSection: {
            eyebrow: 'Frequently Asked Questions',
            title: 'Clear Answers Before You Get Started',
            trialFaq: {
                q: 'How do I get started, and what happens after the trial?',
                a: 'Click "Start Free Trial" and enter your store name. Your dedicated tenant domain and database will be generated in under 60 seconds. You enjoy full access for {days} days with zero obligation, and can upgrade anytime without losing any records.',
            },
            items: [
                {
                    q: 'Do I need to install any software or local server?',
                    a: 'No. The platform is 100% cloud-hosted. It works directly in any modern web browser and can be pinned as a standalone desktop or mobile application.',
                },
                {
                    q: 'Can multiple employees work simultaneously?',
                    a: 'Yes! You can create individual user accounts with distinct role permissions. Changes sync in real-time across all connected terminals.',
                },
                {
                    q: 'Does it support thermal receipt printers?',
                    a: 'Yes, both 80mm and 58mm thermal rolls are natively supported alongside standard A4/A5 office printers, with device-specific print presets.',
                },
                {
                    q: 'How do I notify customers about their repairs?',
                    a: 'The system generates smart WhatsApp notification links with pre-formatted device and status details that you can dispatch in one tap.',
                },
                {
                    q: 'Is my store and accounting data secure?',
                    a: 'All data is isolated in secure tenant databases with automated backups, SSL encryption, and strict access control.',
                },
            ],
        },
        ctaSection: {
            title: 'Ready to Upgrade Your Repair Business?',
            subtitleTrial: 'Launch your store workspace now with full features free for {days} days.',
            subtitleStandard: 'Sign in to your account and manage sales, repairs, and cash in one unified dashboard.',
            buttonTrial: 'Start Your Free Trial Now',
            buttonLogin: 'Sign In to Shop',
            buttonContact: 'Contact Us on WhatsApp',
        },
        factsSection: {
            items: [
                { value: '6', label: 'Stages to track every device' },
                { value: '80 · 58 · A4', label: 'Every receipt size' },
                { value: '< 1', unit: 'min', label: 'To set up a new shop' },
                { value: 'AR · EN', label: 'Arabic & English interface' },
            ],
        },
        footer: {
            product: 'Product',
            account: 'Account',
            copyright: 'All rights reserved.',
            tagline: 'Modern software solutions for repair centers and retail POS.',
        },
        shopFinder: {
            brandName: 'Damatech Repair Hub',
            title: 'Sign in to Your Shop',
            subtitle: 'Each shop has its own dedicated address. Type your shop name as provided in your setup.',
            label: 'Shop Subdomain',
            placeholder: 'myshop',
            submit: 'Continue to Shop',
            noShop: "Don't have a shop yet?",
            createShop: 'Start Free Trial',
            learnMore: 'Discover Platform Features',
            errorFormat: 'Use lowercase letters, numbers, and hyphens only, e.g. alnour',
            backHome: 'Back to Home',
        },
        register: {
            brandName: 'Damatech Repair Hub',
            title: 'Create Your Free Shop',
            subtitle: 'Full {days}-day free trial with no credit card required. Your shop receives a dedicated subdomain and private database.',
            shopSection: 'Shop Details',
            shopNameLabel: 'Shop Name',
            shopNamePlaceholder: 'e.g. Al-Nour Phone & Repair Center',
            slugLabel: 'Shop Subdomain',
            slugPlaceholder: 'alnour',
            slugHint: 'Lowercase letters, numbers, and hyphens (-). This will be your permanent shop address.',
            slugChecking: 'Checking availability...',
            slugAvailable: 'Subdomain is available!',
            slugTaken: 'This subdomain is already taken, please choose another.',
            ownerSection: 'Owner Administrator Account',
            ownerNameLabel: 'Full Name',
            ownerPhoneLabel: 'Mobile Phone Number',
            ownerEmailLabel: 'Email Address (Used for Login)',
            passwordLabel: 'Password',
            passwordConfirmLabel: 'Confirm Password',
            passwordHint: 'At least 8 characters with a mix of letters and numbers.',
            showPassword: 'Show',
            hidePassword: 'Hide',
            termsAgreement: 'By creating a shop, you agree to our Terms of Service and Privacy Policy.',
            submitButton: 'Create Shop & Start Free Trial',
            submittingButton: 'Setting up your shop and database...',
            alreadyHaveShop: 'Already have a shop?',
            loginLink: 'Sign in to your shop',
            successTitle: 'Your Shop is Ready!',
            successSubtitle: 'Your shop was created successfully. Your full-feature trial is active until {date}.',
            savedAddressNotice: 'Your dedicated shop URL — bookmark this address to access your shop:',
            loginCredentialsNotice: 'Sign in with your email address and the password you just created.',
            sslWaitNotice: 'We are provisioning the SSL certificate for your new subdomain, which may take 1-2 minutes. If it does not open immediately, please retry shortly.',
            enterShopButton: 'Open My Shop Now',
        },
    },
};

export function useI18n() {
    const isAr = computed(() => locale.value === LOCALE_AR);
    const isEn = computed(() => locale.value === LOCALE_EN);
    const dir = computed(() => (isAr.value ? 'rtl' : 'ltr'));

    const t = computed(() => messages[locale.value] || messages.ar);

    function setLocale(next) {
        if (next !== LOCALE_AR && next !== LOCALE_EN) return;
        locale.value = next;
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Non-fatal
        }
        applyLocale(next);
    }

    function toggleLocale() {
        setLocale(isAr.value ? LOCALE_EN : LOCALE_AR);
    }

    return {
        locale,
        isAr,
        isEn,
        dir,
        t,
        setLocale,
        toggleLocale,
    };
}
