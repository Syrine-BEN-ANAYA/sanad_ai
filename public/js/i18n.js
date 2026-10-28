/**
 * Minimal i18n: Arabic (default, RTL) and English (LTR).
 * Every entry is [arabic, english].
 */

const T = {
  // brand / nav
  'app.name': ['سند الذكي', 'Sanad AI'],
  'app.tagline': ['منصة إدارة مكاتب سند', 'Sanad office management platform'],
  'nav.dashboard': ['لوحة التحكم', 'Dashboard'],
  'nav.transactions': ['المعاملات', 'Transactions'],
  'nav.clients': ['العملاء', 'Clients'],
  'nav.services': ['الخدمات', 'Services'],
  'nav.payments': ['المدفوعات', 'Payments'],
  'nav.invoices': ['الفواتير', 'Invoices'],
  'nav.attendance': ['الحضور', 'Attendance'],
  'nav.clearance': ['التخليص الخارجي', 'External clearance'],
  'nav.whatsapp': ['واتساب', 'WhatsApp'],
  'nav.discussions': ['المناقشات', 'Discussions'],
  'nav.reports': ['التقارير والذكاء الاصطناعي', 'Reports & AI'],
  'nav.users': ['المستخدمون', 'Users'],
  'nav.settings': ['الإعدادات', 'Settings'],
  'nav.logout': ['تسجيل الخروج', 'Sign out'],
  'nav.menu': ['القائمة', 'Menu'],
  'theme.toggle': ['تبديل المظهر', 'Toggle theme'],
  'lang.toggle': ['English', 'العربية'],

  // login
  'login.title': ['تسجيل الدخول', 'Sign in'],
  'login.subtitle': ['أدخل بيانات حسابك للوصول إلى النظام', 'Enter your credentials to access the system'],
  'login.username': ['اسم المستخدم', 'Username'],
  'login.password': ['كلمة المرور', 'Password'],
  'login.submit': ['دخول', 'Sign in'],
  'login.show': ['إظهار كلمة المرور', 'Show password'],
  'login.hide': ['إخفاء كلمة المرور', 'Hide password'],
  'login.hero': ['كل معاملات مكتبك في مكان واحد', 'Every transaction of your office, in one place'],
  'login.lead': ['تابع المعاملات الحكومية والعملاء والمستندات بسرعة ووضوح، مع صلاحيات دقيقة لكل موظف.', 'Track government transactions, clients and documents with clarity — and precise permissions for every employee.'],
  'login.p1': ['تتبّع حالة كل معاملة خطوة بخطوة', 'Follow every transaction step by step'],
  'login.p2': ['مستندات العملاء محفوظة بأمان', 'Client documents stored securely'],
  'login.p3': ['صلاحيات حسب دور الموظف', 'Role-based access for every employee'],
  'login.demo.title': ['حسابات تجريبية', 'Demo accounts'],
  'login.demo.hint': ['اضغط على حساب لتعبئة بيانات الدخول', 'Click an account to fill in the credentials'],
  'login.demo.password': ['كلمة المرور', 'Password'],
  'login.footer': ['جميع الحقوق محفوظة', 'All rights reserved'],

  // common
  'common.search': ['بحث…', 'Search…'],
  'common.all': ['الكل', 'All'],
  'common.save': ['حفظ', 'Save'],
  'common.cancel': ['إلغاء', 'Cancel'],
  'common.close': ['إغلاق', 'Close'],
  'common.create': ['إنشاء', 'Create'],
  'common.edit': ['تعديل', 'Edit'],
  'common.delete': ['حذف', 'Delete'],
  'common.deactivate': ['تعطيل', 'Deactivate'],
  'common.confirm': ['تأكيد', 'Confirm'],
  'common.actions': ['إجراءات', 'Actions'],
  'common.status': ['الحالة', 'Status'],
  'common.loading': ['جارٍ التحميل…', 'Loading…'],
  'common.retry': ['إعادة المحاولة', 'Retry'],
  'common.back': ['رجوع', 'Back'],
  'common.none': ['—', '—'],
  'common.saved': ['تم الحفظ بنجاح', 'Saved successfully'],
  'common.created': ['تم الإنشاء بنجاح', 'Created successfully'],
  'common.error': ['حدث خطأ غير متوقع', 'An unexpected error occurred'],
  'common.network': ['تعذّر الاتصال بالخادم', 'Unable to reach the server'],
  'common.noaccess': ['ليست لديك صلاحية لعرض هذه الصفحة', 'You do not have permission to view this page'],
  'common.showing': ['عرض', 'Showing'],
  'common.of': ['من', 'of'],
  'common.prev': ['السابق', 'Previous'],
  'common.next': ['التالي', 'Next'],
  'common.active': ['نشط', 'Active'],
  'common.inactive': ['غير نشط', 'Inactive'],
  'common.notes': ['ملاحظات', 'Notes'],
  'common.created_at': ['تاريخ الإنشاء', 'Created'],
  'common.omr': ['ر.ع.', 'OMR'],
  'common.minutes': ['دقيقة', 'min'],
  'common.hours': ['ساعة', 'h'],
  'common.days': ['يوم', 'd'],
  'common.no_results': ['لا توجد نتائج', 'No results'],
  'common.no_results_hint': ['جرّب تغيير كلمات البحث أو عوامل التصفية', 'Try different search terms or filters'],
  'common.office': ['المكتب', 'Office'],


  'module.payments.title': ['المدفوعات', 'Payments'],
  'module.invoices.title': ['الفواتير', 'Invoices'],
  'module.attendance.title': ['الحضور والانصراف', 'Attendance'],
  'module.clearance.title': ['التخليص الخارجي', 'External clearance'],
  'module.whatsapp.title': ['محادثات واتساب', 'WhatsApp conversations'],
  'module.discussions.title': ['مناقشات الفريق', 'Team discussions'],
  'module.reports.title': ['التقارير والذكاء الاصطناعي', 'Reports & AI'],
  'module.new': ['إضافة سجل', 'Add record'],
  'module.search': ['بحث في السجلات…', 'Search records…'],
  'module.empty': ['لا توجد سجلات', 'No records yet'],
  'module.type': ['النوع', 'Type'],
  'module.amount': ['المبلغ', 'Amount'],
  'module.status': ['الحالة', 'Status'],
  'module.reference': ['المرجع', 'Reference'],
  'module.notes': ['الملاحظات', 'Notes'],
  'module.date': ['التاريخ', 'Date'],
  'module.authority': ['الجهة', 'Authority'],
  'module.phone': ['الهاتف', 'Phone'],
  'module.message': ['الرسالة', 'Message'],
  'module.invoice_number': ['رقم الفاتورة', 'Invoice number'],
  'module.user': ['الموظف', 'Employee'],
  'module.report_from': ['من', 'From'],
  'module.report_to': ['إلى', 'To'],
  'module.refresh': ['تحديث', 'Refresh'],

  // dashboard
  'dash.title': ['لوحة التحكم', 'Dashboard'],
  'dash.welcome': ['مرحباً', 'Welcome back'],
  'dash.total': ['إجمالي المعاملات', 'Total transactions'],
  'dash.active': ['قيد المعالجة', 'In progress'],
  'dash.overdue': ['متأخرة', 'Overdue'],
  'dash.overdue_note': ['تجاوزت موعد الاستحقاق', 'Past their due date'],
  'dash.completed_month': ['مكتملة هذا الشهر', 'Completed this month'],
  'dash.fees_month': ['رسوم المكتب هذا الشهر', 'Office fees this month'],
  'dash.by_status': ['توزيع المعاملات حسب الحالة', 'Transactions by status'],
  'dash.recent': ['آخر المعاملات', 'Latest transactions'],
  'dash.view_all': ['عرض الكل', 'View all'],
  'dash.empty': ['لا توجد معاملات بعد', 'No transactions yet'],
  'dash.empty_hint': ['ابدأ بإنشاء أول معاملة لعميل.', 'Start by creating the first transaction for a client.'],

  // transactions
  'tx.title': ['المعاملات', 'Transactions'],
  'tx.new': ['معاملة جديدة', 'New transaction'],
  'tx.edit': ['تعديل المعاملة', 'Edit transaction'],
  'tx.reference': ['المرجع', 'Reference'],
  'tx.subject': ['العنوان', 'Title'],
  'tx.client': ['العميل', 'Client'],
  'tx.service': ['الخدمة', 'Service'],
  'tx.assignee': ['المسؤول', 'Assignee'],
  'tx.unassigned': ['غير مُسنَدة', 'Unassigned'],
  'tx.priority': ['الأولوية', 'Priority'],
  'tx.due': ['موعد الاستحقاق', 'Due date'],
  'tx.opened': ['تاريخ الفتح', 'Opened'],
  'tx.description': ['الوصف', 'Description'],
  'tx.source': ['المصدر', 'Source'],
  'tx.fees': ['الرسوم', 'Fees'],
  'tx.official_fee': ['الرسوم الرسمية', 'Official fee'],
  'tx.office_fee': ['رسوم المكتب', 'Office fee'],
  'tx.total_fee': ['الإجمالي', 'Total'],
  'tx.overdue_only': ['المتأخرة فقط', 'Overdue only'],
  'tx.details': ['تفاصيل المعاملة', 'Transaction details'],
  'tx.history': ['سجل الحالات', 'Status history'],
  'tx.change_status': ['تغيير الحالة', 'Change status'],
  'tx.move_to': ['نقل إلى', 'Move to'],
  'tx.reason': ['السبب (اختياري)', 'Reason (optional)'],
  'tx.closed': ['هذه المعاملة مغلقة ولا يمكن تعديلها', 'This transaction is closed and cannot be edited'],
  'tx.status_changed': ['تم تغيير الحالة', 'Status updated'],
  'tx.pick_client': ['اختر العميل…', 'Pick a client…'],
  'tx.pick_service': ['اختر الخدمة…', 'Pick a service…'],
  'tx.pick_hint': ['اكتب للبحث', 'Type to search'],
  'tx.by': ['بواسطة', 'by'],
  'tx.created_note': ['تم إنشاء المعاملة', 'Transaction created'],

  // statuses
  'status.inquiry': ['استفسار', 'Inquiry'],
  'status.open': ['مفتوحة', 'Open'],
  'status.waiting_documents': ['بانتظار المستندات', 'Waiting for documents'],
  'status.in_progress': ['قيد التنفيذ', 'In progress'],
  'status.waiting_payment': ['بانتظار الدفع', 'Waiting for payment'],
  'status.waiting_external': ['بانتظار جهة حكومية', 'Waiting on authority'],
  'status.waiting_client': ['بانتظار العميل', 'Waiting for client'],
  'status.completed': ['مكتملة', 'Completed'],
  'status.cancelled': ['ملغاة', 'Cancelled'],
  'priority.normal': ['عادية', 'Normal'],
  'priority.high': ['مرتفعة', 'High'],
  'priority.urgent': ['عاجلة', 'Urgent'],
  'source.dashboard': ['لوحة التحكم', 'Dashboard'],
  'source.whatsapp': ['واتساب', 'WhatsApp'],
  'source.converted_inquiry': ['استفسار محوَّل', 'Converted inquiry'],

  // documents
  'doc.title': ['المستندات', 'Documents'],
  'doc.upload': ['رفع مستند', 'Upload document'],
  'doc.type': ['نوع المستند', 'Document type'],
  'doc.file': ['الملف', 'File'],
  'doc.file_hint': ['PDF أو صورة، حتى 10 ميجابايت', 'PDF or image, up to 10 MB'],
  'doc.none': ['لا توجد مستندات مرفوعة', 'No documents uploaded'],
  'doc.download': ['تنزيل', 'Download'],
  'doc.verify': ['اعتماد', 'Verify'],
  'doc.reject': ['رفض', 'Reject'],
  'doc.reject_reason': ['سبب الرفض', 'Rejection reason'],
  'doc.uploaded': ['تم رفع المستند', 'Document uploaded'],
  'doc.updated': ['تم تحديث حالة المستند', 'Document status updated'],
  'doc.choose': ['اختر ملفاً', 'Choose a file'],
  'doc.type.passport': ['جواز سفر', 'Passport'],
  'doc.type.id_card': ['بطاقة شخصية', 'ID card'],
  'doc.type.resident_card': ['بطاقة مقيم', 'Resident card'],
  'doc.type.commercial_register': ['سجل تجاري', 'Commercial register'],
  'doc.type.contract': ['عقد', 'Contract'],
  'doc.type.power_of_attorney': ['وكالة', 'Power of attorney'],
  'doc.type.photo': ['صورة شخصية', 'Photo'],
  'doc.type.other': ['أخرى', 'Other'],
  'docstatus.required': ['مطلوب', 'Required'],
  'docstatus.received': ['مستلم', 'Received'],
  'docstatus.verified': ['معتمد', 'Verified'],
  'docstatus.rejected': ['مرفوض', 'Rejected'],
  'docstatus.expired': ['منتهي', 'Expired'],

  // clients
  'client.title': ['العملاء', 'Clients'],
  'client.new': ['عميل جديد', 'New client'],
  'client.edit': ['تعديل العميل', 'Edit client'],
  'client.name': ['الاسم الكامل', 'Full name'],
  'client.type': ['النوع', 'Type'],
  'client.person': ['فرد', 'Individual'],
  'client.company': ['شركة', 'Company'],
  'client.company_name': ['اسم الشركة', 'Company name'],
  'client.phone': ['الهاتف', 'Phone'],
  'client.whatsapp': ['واتساب', 'WhatsApp'],
  'client.email': ['البريد الإلكتروني', 'Email'],
  'client.language': ['لغة التواصل', 'Preferred language'],
  'client.contact': ['التواصل', 'Contact'],
  'client.deactivate_q': ['هل تريد تعطيل هذا العميل؟', 'Deactivate this client?'],
  'client.deactivated': ['تم تعطيل العميل', 'Client deactivated'],
  'client.search': ['ابحث بالاسم أو الهاتف أو البريد…', 'Search by name, phone or email…'],
  'lang.ar': ['العربية', 'Arabic'],
  'lang.en': ['الإنجليزية', 'English'],

  // services
  'svc.title': ['الخدمات', 'Services'],
  'svc.new': ['خدمة جديدة', 'New service'],
  'svc.edit': ['تعديل الخدمة', 'Edit service'],
  'svc.name_ar': ['الاسم بالعربية', 'Arabic name'],
  'svc.name_en': ['الاسم بالإنجليزية', 'English name'],
  'svc.desc_ar': ['الوصف بالعربية', 'Arabic description'],
  'svc.desc_en': ['الوصف بالإنجليزية', 'English description'],
  'svc.ministry': ['الجهة الحكومية', 'Authority'],
  'svc.no_ministry': ['بدون جهة', 'No authority'],
  'svc.official_fee': ['الرسوم الرسمية (ر.ع.)', 'Official fee (OMR)'],
  'svc.office_fee': ['رسوم المكتب (ر.ع.)', 'Office fee (OMR)'],
  'svc.sla': ['مدة الإنجاز المستهدفة (دقائق)', 'Target turnaround (minutes)'],
  'svc.sla_short': ['مدة الإنجاز', 'Turnaround'],
  'svc.deactivate_q': ['هل تريد تعطيل هذه الخدمة؟', 'Deactivate this service?'],
  'svc.deactivated': ['تم تعطيل الخدمة', 'Service deactivated'],
  'svc.search': ['ابحث باسم الخدمة…', 'Search by service name…'],

  // users
  'user.title': ['المستخدمون', 'Users'],
  'user.new': ['مستخدم جديد', 'New user'],
  'user.edit': ['تعديل المستخدم', 'Edit user'],
  'user.full_name': ['الاسم الكامل', 'Full name'],
  'user.username': ['اسم المستخدم', 'Username'],
  'user.password': ['كلمة المرور', 'Password'],
  'user.password_hint': ['12 حرفاً على الأقل', 'At least 12 characters'],
  'user.role': ['الدور', 'Role'],
  'user.last_login': ['آخر دخول', 'Last sign-in'],
  'user.never': ['لم يسجّل الدخول بعد', 'Never signed in'],
  'user.search': ['ابحث بالاسم أو اسم المستخدم…', 'Search by name or username…'],
  'user.status.active': ['نشط', 'Active'],
  'user.status.inactive': ['موقوف', 'Suspended'],
  'user.status.invited': ['مدعو', 'Invited'],
  'user.status.locked': ['مقفل', 'Locked'],
  'role.super_admin': ['مدير المنصة', 'Super admin'],
  'role.manager': ['مدير المكتب', 'Manager'],
  'role.customer_service': ['خدمة العملاء', 'Customer service'],
  'role.accountant': ['محاسب', 'Accountant'],
  'role.clearance_employee': ['موظف تخليص', 'Clearance employee'],

  // settings
  'set.title': ['الإعدادات', 'Settings'],
  'set.profile': ['الحساب', 'Account'],
  'set.appearance': ['المظهر واللغة', 'Appearance & language'],
  'set.theme': ['المظهر', 'Theme'],
  'set.theme_light': ['فاتح', 'Light'],
  'set.theme_dark': ['داكن', 'Dark'],
  'set.language': ['اللغة', 'Language'],
  'set.password': ['تغيير كلمة المرور', 'Change password'],
  'set.current_password': ['كلمة المرور الحالية', 'Current password'],
  'set.new_password': ['كلمة المرور الجديدة', 'New password'],
  'set.confirm_password': ['تأكيد كلمة المرور الجديدة', 'Confirm new password'],
  'set.mismatch': ['كلمتا المرور غير متطابقتين', 'Passwords do not match'],
  'set.password_changed': ['تم تغيير كلمة المرور', 'Password changed'],
  'set.permissions': ['صلاحياتي', 'My permissions'],

  // platform admin
  'platform.title': ['حساب مدير المنصة', 'Platform administrator account'],
  'platform.body': ['هذا الحساب غير مرتبط بمكتب، لذلك لا تتوفر بيانات المكتب. أنشئ المكاتب ومديريها من سطر الأوامر: php scripts/create_tenant.php', 'This account is not tied to an office, so there is no office data to show. Create offices and their managers from the command line: php scripts/create_tenant.php'],
};

/** Known server messages -> Arabic. Anything else is shown as received. */
const SERVER_MESSAGES_AR = {
  'Invalid credentials.': 'اسم المستخدم أو كلمة المرور غير صحيحة.',
  'Too many login attempts. Please try again later.': 'محاولات دخول كثيرة. حاول مرة أخرى لاحقاً.',
  'Authentication required.': 'يجب تسجيل الدخول.',
  'Session expired.': 'انتهت الجلسة، سجّل الدخول من جديد.',
  'Invalid CSRF token.': 'رمز الأمان غير صالح، أعد تحميل الصفحة.',
  'CSRF token required.': 'رمز الأمان مطلوب، أعد تحميل الصفحة.',
  'Client not found.': 'العميل غير موجود.',
  'Service not found.': 'الخدمة غير موجودة.',
  'Transaction not found.': 'المعاملة غير موجودة.',
  'Document not found.': 'المستند غير موجود.',
  'User not found.': 'المستخدم غير موجود.',
  'Invalid email address.': 'البريد الإلكتروني غير صالح.',
  'Full name is required.': 'الاسم الكامل مطلوب.',
  'Company name is required for company clients.': 'اسم الشركة مطلوب لعملاء الشركات.',
  'Arabic service name is required.': 'اسم الخدمة بالعربية مطلوب.',
  'Transaction title is required.': 'عنوان المعاملة مطلوب.',
  'Client is inactive.': 'العميل غير نشط.',
  'Service is inactive.': 'الخدمة غير نشطة.',
  'Current password is incorrect.': 'كلمة المرور الحالية غير صحيحة.',
  'Document type is required.': 'نوع المستند مطلوب.',
  'Document file is required.': 'الملف مطلوب.',
  'Uploaded file is too large.': 'حجم الملف كبير جداً.',
  'Rejection reason is required.': 'سبب الرفض مطلوب.',
  'Completed or cancelled transactions cannot be edited.': 'لا يمكن تعديل معاملة مكتملة أو ملغاة.',
  'Transaction is already in this status.': 'المعاملة في هذه الحالة بالفعل.',
  'Tenant context is required.': 'هذا الحساب غير مرتبط بمكتب.',
};

const STORAGE_KEY = 'sanad.lang';

function read(key) {
  try { return localStorage.getItem(key); } catch { return null; }
}
function write(key, value) {
  try { localStorage.setItem(key, value); } catch { /* storage unavailable */ }
}

let lang = read(STORAGE_KEY) === 'en' ? 'en' : 'ar';

export const getLang = () => lang;
export const isRtl = () => lang === 'ar';

export function setLang(next) {
  write(STORAGE_KEY, next === 'en' ? 'en' : 'ar');
}

export function applyDocumentLang() {
  document.documentElement.lang = lang;
  document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
}

export function t(key) {
  const entry = T[key];
  if (!entry) return key;
  return entry[lang === 'ar' ? 0 : 1];
}

export function has(key) {
  return key in T;
}

/** Translate a server message when we know it (Arabic UI only). */
export function serverMessage(message) {
  if (!message) return t('common.error');
  if (lang === 'ar') {
    if (SERVER_MESSAGES_AR[message]) return SERVER_MESSAGES_AR[message];
    if (message.startsWith('Permission denied')) return 'ليست لديك صلاحية لتنفيذ هذا الإجراء.';
    if (message.startsWith('Invalid status transition')) return 'لا يمكن الانتقال إلى هذه الحالة من الحالة الحالية.';
  }
  return message;
}

/** For tests: every key referenced in code must exist. */
export const _keys = () => Object.keys(T);
