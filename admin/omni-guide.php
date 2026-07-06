<?php
require_once('header.php');
?>

<style>
/* Modern SaaS Styling for the Guides page */
.guide-container {
    padding: 20px;
    font-family: 'InterLocal', 'CairoLocal', sans-serif;
    direction: rtl;
    text-align: right;
}
.guide-header {
    margin-bottom: 30px;
    text-align: center;
}
.guide-header h2 {
    font-size: 2.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 10px;
}
.guide-header p {
    color: #64748b;
    font-size: 1.1rem;
}

/* Tabs */
.guide-tabs {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 20px;
}
.guide-tab {
    padding: 12px 24px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 10px;
    font-weight: 700;
    font-size: 1.05rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 10px;
}
.guide-tab:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.guide-tab.active {
    background: #3b82f6;
    color: white;
    box-shadow: 0 6px 15px -3px rgba(59, 130, 246, 0.4);
}
/* Per-platform active colors */
.guide-tab[onclick*="'meta'"].active {
    background: #1877f2;
    box-shadow: 0 6px 15px -3px rgba(24, 119, 242, 0.4);
}
.guide-tab[onclick*="'instagram'"].active {
    background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
    box-shadow: 0 6px 15px -3px rgba(220, 39, 67, 0.4);
}
.guide-tab[onclick*="'whatsapp'"].active {
    background: #25d366;
    box-shadow: 0 6px 15px -3px rgba(37, 211, 102, 0.4);
}
.guide-tab[onclick*="'n8n'"].active {
    background: #ea445a;
    box-shadow: 0 6px 15px -3px rgba(234, 68, 90, 0.4);
}
.guide-tab[onclick*="'marketing'"].active {
    background: #8b5cf6;
    box-shadow: 0 6px 15px -3px rgba(139, 92, 246, 0.4);
}

/* Content */
.guide-content {
    display: none;
    animation: fadeIn 0.4s ease;
}
.guide-content.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Step Cards */
.step-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 25px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s;
}
.step-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
}
.step-number {
    position: absolute;
    top: 0;
    right: 0;
    background: #3b82f6;
    color: white;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    font-weight: bold;
    border-bottom-left-radius: 16px;
}
.step-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: #1e293b;
    margin-top: 5px;
    margin-bottom: 15px;
    padding-right: 40px;
}
.step-desc {
    color: #475569;
    font-size: 1.05rem;
    line-height: 1.8;
    margin-bottom: 15px;
}
.step-desc ul {
    padding-right: 25px;
    margin-top: 15px;
}
.step-desc li {
    margin-bottom: 10px;
}
.step-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.2s;
    border: 1px solid #bfdbfe;
}
.step-link:hover {
    background: #dbeafe;
    text-decoration: none;
    color: #1e40af;
}

/* specific colors for step numbers */
.meta-theme .step-number { background: #1877f2; }
.meta-theme .step-link { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
.meta-theme .step-link:hover { background: #e0e7ff; }

.ig-theme .step-number { background: #e1306c; }
.ig-theme .step-link { background: #fdf2f8; color: #be185d; border-color: #fbcfe8; }
.ig-theme .step-link:hover { background: #fce7f3; }

.wa-theme .step-number { background: #25d366; }
.wa-theme .step-link { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.wa-theme .step-link:hover { background: #dcfce7; }

.n8n-theme .step-number { background: #ea445a; }
.n8n-theme .step-link { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.n8n-theme .step-link:hover { background: #ffe4e6; }

.mk-theme .step-number { background: #8b5cf6; }
.mk-theme .step-link { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
.mk-theme .step-link:hover { background: #ede9fe; }

.code-box {
    background: #0f172a;
    color: #10b981;
    padding: 20px;
    border-radius: 10px;
    direction: ltr;
    text-align: left;
    font-family: monospace;
    margin-top: 15px;
    font-size: 0.95rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
}

.alert-premium {
    background: linear-gradient(to right, #f8fafc, #f1f5f9);
    border-right: 4px solid #8b5cf6;
    border-radius: 12px;
    padding: 20px;
    color: #334155;
    font-size: 1.1rem;
    line-height: 1.6;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    margin-bottom: 25px;
}
</style>

        <div class="guide-container">
            <div class="guide-header">
                <h2><i class="fa fa-book text-primary"></i> دليل الاستخدام والربط الشامل</h2>
                <p>دليلك المتكامل لربط المنصات، بناء الأتمتة، والتحكم بمحرك الذكاء الاصطناعي</p>
            </div>

            <div class="guide-tabs">
                <button class="guide-tab active" onclick="switchTab('meta')">
                    <i class="fa fa-facebook-official"></i> إعداد Messenger
                </button>
                <button class="guide-tab" onclick="switchTab('instagram')">
                    <i class="fa fa-instagram"></i> إعداد Instagram
                </button>
                <button class="guide-tab" onclick="switchTab('whatsapp')">
                    <i class="fa fa-whatsapp"></i> إعداد WhatsApp
                </button>
                <button class="guide-tab" onclick="switchTab('n8n')">
                    <i class="fa fa-cogs"></i> إعداد n8n
                </button>
                <button class="guide-tab" onclick="switchTab('marketing')">
                    <i class="fa fa-rocket"></i> Marketing Engine & AI
                </button>
            </div>

            <!-- Meta Guide -->
            <div id="tab-meta" class="guide-content active meta-theme">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="step-title">إنشاء تطبيق جديد في Meta</h3>
                    <div class="step-desc">
                        للبدء في ربط صفحتك، تحتاج إلى إنشاء تطبيق على منصة المطورين من ميتا.
                        <ul>
                            <li>سجل الدخول إلى حسابك في فيسبوك.</li>
                            <li>اذهب إلى لوحة تحكم المطورين واضغط على <strong>Create App</strong> (إنشاء تطبيق).</li>
                            <li>اختر النوع <strong>Business</strong> (أعمال) أو <strong>Other</strong>.</li>
                            <li>قم بتسمية التطبيق (مثال: متجري للرد الآلي) واضغط إنشاء.</li>
                        </ul>
                    </div>
                    <a href="https://developers.facebook.com/apps" target="_blank" class="step-link">
                        <i class="fa fa-external-link"></i> الذهاب إلى Meta for Developers
                    </a>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3 class="step-title">إضافة منتج Messenger (الماسنجر)</h3>
                    <div class="step-desc">
                        بعد إنشاء التطبيق، سيوجهك لصفحة المنتجات (Products).
                        <ul>
                            <li>ابحث عن بطاقة <strong>Messenger</strong> واضغط على <strong>Set Up</strong> (إعداد).</li>
                            <li>في القائمة الجانبية اليسرى، ستجد قسم <strong>Messenger</strong>، تحته اضغط على <strong>API Settings</strong>.</li>
                        </ul>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3 class="step-title">إعداد Webhook والاشتراك في الأحداث</h3>
                    <div class="step-desc">
                        الـ Webhook هو الرابط الذي سيرسل إليه فيسبوك الرسائل التي تصل لصفحتك لكي يعالجها موقعك.
                        <ul>
                            <li>انزل لقسم <strong>Webhooks</strong> واضغط <strong>Add Callback URL</strong>.</li>
                            <li>الصق رابط الـ Webhook الخاص بموقعك واكتب الرمز السري <code>Verify Token</code>.</li>
                            <li>اضغط زر <strong>Add Subscriptions</strong> بجانب اسم صفحتك وضع علامة (☑️) على <code>messages</code> و <code>messaging_postbacks</code>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Instagram Guide -->
            <div id="tab-instagram" class="guide-content ig-theme">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="step-title">ربط حساب Instagram بصفحة الفيسبوك</h3>
                    <div class="step-desc">
                        يجب أن يكون حساب الانستغرام الخاص بك حساباً احترافياً ومربوطاً بصفحة الفيسبوك.
                        <ul>
                            <li>افتح تطبيق Instagram واذهب إلى الإعدادات > الحساب > التبديل إلى حساب احترافي.</li>
                            <li>اذهب إلى "تعديل الملف الشخصي" (Edit Profile) واربط الحساب بصفحتك على فيسبوك.</li>
                        </ul>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3 class="step-title">السماح بالوصول إلى الرسائل</h3>
                    <div class="step-desc">
                        هذه الخطوة هامة جداً ليتمكن الـ API من قراءة رسائل الانستغرام.
                        <ul>
                            <li>من تطبيق Instagram، اذهب إلى <strong>الإعدادات والخصوصية</strong>.</li>
                            <li>ابحث عن قسم <strong>الرسائل والردود على القصص</strong>.</li>
                            <li>ادخل إلى <strong>التحكم في الرسائل</strong> وفعل <strong>السماح بالوصول إلى الرسائل</strong>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Guide -->
            <div id="tab-whatsapp" class="guide-content wa-theme">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="step-title">إضافة منتج WhatsApp إلى التطبيق</h3>
                    <div class="step-desc">
                        لإرسال واستقبال رسائل واتساب، ستحتاج إلى استخدام <strong>WhatsApp Cloud API</strong>.
                        <ul>
                            <li>في تطبيقك على <strong>Meta for Developers</strong>، اضغط على <strong>Add Product</strong>.</li>
                            <li>ابحث عن <strong>WhatsApp</strong> ثم اضغط <strong>Set Up</strong>.</li>
                        </ul>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3 class="step-title">الحصول على الرموز وإعداد Webhook</h3>
                    <div class="step-desc">
                        <ul>
                            <li>انسخ <strong>Phone Number ID</strong> و <strong>Access Token</strong> والصقهم في لوحة تحكم المتجر.</li>
                            <li>في قسم <strong>Configuration</strong> داخل WhatsApp، أضف رابط الـ Webhook الخاص بموقعك واشترك في حدث <code>messages</code>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- n8n Guide -->
            <div id="tab-n8n" class="guide-content n8n-theme">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="step-title">الربط مع الذكاء الاصطناعي عبر n8n</h3>
                    <div class="step-desc">
                        <strong>n8n</strong> هي منصة أتمتة قوية تتيح لك ربط خدماتك بالذكاء الاصطناعي (مثل OpenAI أو Gemini).
                        <ul>
                            <li>استخدم عقدة <strong>Webhook</strong> لاستقبال الرسائل القادمة من المتجر.</li>
                            <li>مرر النص إلى عقدة الذكاء الاصطناعي (OpenAI/Gemini).</li>
                            <li>أعد إرسال الرد المولد إلى المتجر أو إلى Meta API مباشرة باستخدام عقدة <strong>HTTP Request</strong>.</li>
                        </ul>
                    </div>
                    <a href="https://n8n.io/" target="_blank" class="step-link">
                        <i class="fa fa-external-link"></i> الموقع الرسمي لـ n8n
                    </a>
                </div>
            </div>

            <!-- Marketing AI Guide -->
            <div id="tab-marketing" class="guide-content mk-theme">
                <div class="alert-premium">
                    <strong><i class="fa fa-shield"></i> الأمان أولاً (Enterprise Hexagonal Architecture):</strong><br>
                    الذكاء الاصطناعي يعمل في نظامنا ضمن بيئة معزولة تماماً ولا يملك صلاحية الاتصال المباشر بقاعدة البيانات أو واجهات Meta. كافة قراراته تمر عبر "طبقات حماية" (Guardrails) لضمان عدم حدوث كوارث في ميزانيتك.
                </div>

                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="step-title">كيف يعمل محرك التسويق (Marketing Engine)؟</h3>
                    <div class="step-desc">
                        الـ Marketing Engine هو الواجهة الرسمية بين متجرك ومنصة الإعلانات (Meta Ads).
                        <ul>
                            <li><strong>Dashboard:</strong> من خلال صفحة Marketing Center، يمكنك مزامنة الحملات، وتعديل الميزانيات بأمان.</li>
                            <li><strong>Safe Migration:</strong> لا يوجد خطر من فقدان بياناتك، حيث يتم الاحتفاظ بسجلات الأحداث (Audit Logs) بشكل دوري.</li>
                        </ul>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3 class="step-title">دور الـ Marketing AI (المستشار الآلي)</h3>
                    <div class="step-desc">
                        يعمل الذكاء الاصطناعي بمبدأ <strong>المستشار (Advisor)</strong> الذي يعطيك توصيات دقيقة لتحسين الـ ROAS (العائد على الإنفاق الإعلاني).
                        <ul>
                            <li><strong>سياسة الثقة (Confidence Policy):</strong> التوصيات ذات الثقة أعلى من 90% تنتظر موافقتك. أما أقل من 90% فهي نصائح للاطلاع فقط.</li>
                            <li><strong>المحاكاة (Simulation):</strong> قبل تطبيق التوصية، يعرض لك النظام الأثر المالي المتوقع (Expected Impact).</li>
                        </ul>
                    </div>
                    <a href="marketing-ai.php" class="step-link">
                        <i class="fa fa-brain"></i> الذهاب إلى لوحة الذكاء الاصطناعي
                    </a>
                </div>
            </div>

        </div>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.guide-tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.guide-content').forEach(el => el.classList.remove('active'));
    
    document.querySelectorAll('.guide-tab').forEach(el => {
        if(el.getAttribute('onclick').includes(tabName)) {
            el.classList.add('active');
        }
    });
    
    const contentId = 'tab-' + tabName;
    const contentEl = document.getElementById(contentId);
    if(contentEl) {
        contentEl.classList.add('active');
    }
}
</script>

<?php require_once('footer.php'); ?>
