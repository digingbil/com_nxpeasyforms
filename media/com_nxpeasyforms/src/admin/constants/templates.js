import { __ } from '@/utils/translate';

export const FORM_TEMPLATES = [
    {
        id: 'contact-simple',
        name: __('Simple Contact Form', 'nxp-easy-forms'),
        description: __(
            'Name, email, and message - the basics',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'name',
                label: __('Your Name', 'nxp-easy-forms'),
                placeholder: __('Jane Doe', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email Address', 'nxp-easy-forms'),
                placeholder: __('jane@example.com', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'textarea',
                name: 'message',
                label: __('Message', 'nxp-easy-forms'),
                placeholder: __('How can we help you?', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'button',
                label: __('Send Message', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                "Thank you! We'll get back to you soon.",
                'nxp-easy-forms'
            ),
        },
    },
    {
        id: 'contact-business',
        name: __('Business Contact Form', 'nxp-easy-forms'),
        description: __(
            'Professional contact form with company details',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'first_name',
                label: __('First Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'text',
                name: 'last_name',
                label: __('Last Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Work Email', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'tel',
                name: 'phone',
                label: __('Phone Number', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'text',
                name: 'company',
                label: __('Company Name', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'select',
                name: 'company_size',
                label: __('Company Size', 'nxp-easy-forms'),
                required: false,
                options: [
                    '1-10 employees',
                    '11-50 employees',
                    '51-200 employees',
                    '200+ employees',
                ],
            },
            {
                type: 'select',
                name: 'inquiry_type',
                label: __('How can we help?', 'nxp-easy-forms'),
                required: true,
                options: [
                    'Sales Inquiry',
                    'Technical Support',
                    'Partnership',
                    'Other',
                ],
            },
            {
                type: 'textarea',
                name: 'message',
                label: __('Message', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'button',
                label: __('Submit Inquiry', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                "Thank you for your inquiry! We'll respond soon.",
                'nxp-easy-forms'
            ),
        },
    },
    {
        id: 'newsletter',
        name: __('Newsletter Signup', 'nxp-easy-forms'),
        description: __(
            'Email subscription with consent checkbox',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'email',
                name: 'email',
                label: __('Email Address', 'nxp-easy-forms'),
                placeholder: __('your@email.com', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'text',
                name: 'first_name',
                label: __('First Name (Optional)', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'checkbox',
                name: 'consent',
                label: __('I agree to receive email updates', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'button',
                label: __('Subscribe', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                "You're subscribed! Check your email to confirm.",
                'nxp-easy-forms'
            ),
        },
    },
    {
        id: 'job-application',
        name: __('Job Application Form', 'nxp-easy-forms'),
        description: __(
            'Complete application with file upload for resume',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'full_name',
                label: __('Full Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email Address', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'tel',
                name: 'phone',
                label: __('Phone Number', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'text',
                name: 'linkedin',
                label: __('LinkedIn Profile URL', 'nxp-easy-forms'),
                placeholder: 'https://linkedin.com/in/yourprofile',
                required: false,
            },
            {
                type: 'select',
                name: 'position',
                label: __('Position Applied For', 'nxp-easy-forms'),
                required: true,
                options: [
                    'Frontend Developer',
                    'Backend Developer',
                    'Full Stack Developer',
                    'Designer',
                ],
            },
            {
                type: 'file',
                name: 'resume',
                label: __('Resume/CV', 'nxp-easy-forms'),
                required: true,
                accept: 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                maxFileSize: 5,
            },
            {
                type: 'file',
                name: 'cover_letter',
                label: __('Cover Letter (Optional)', 'nxp-easy-forms'),
                required: false,
                accept: 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                maxFileSize: 2,
            },
            {
                type: 'textarea',
                name: 'why_you',
                label: __('Why do you want to work with us?', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'button',
                label: __('Submit Application', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                "Application received! We'll review and contact you soon.",
                'nxp-easy-forms'
            ),
        },
    },
    {
        id: 'appointment-request',
        name: __('Appointment Request', 'nxp-easy-forms'),
        description: __(
            'Schedule appointments with preferred date and service',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'full_name',
                label: __('Full Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'tel',
                name: 'phone',
                label: __('Phone Number', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'date',
                name: 'preferred_date',
                label: __('Preferred Date', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'select',
                name: 'preferred_time',
                label: __('Preferred Time', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('09:00 AM - 10:00 AM', 'nxp-easy-forms'),
                    __('10:00 AM - 11:00 AM', 'nxp-easy-forms'),
                    __('11:00 AM - 12:00 PM', 'nxp-easy-forms'),
                    __('01:00 PM - 02:00 PM', 'nxp-easy-forms'),
                    __('02:00 PM - 03:00 PM', 'nxp-easy-forms'),
                    __('03:00 PM - 04:00 PM', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'service',
                label: __('Service Interested In', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Consultation (30 min)', 'nxp-easy-forms'),
                    __('Full Service (60 min)', 'nxp-easy-forms'),
                    __('Follow-up (15 min)', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'textarea',
                name: 'notes',
                label: __('Additional Notes', 'nxp-easy-forms'),
                required: false,
                placeholder: __('Share any extra details', 'nxp-easy-forms'),
            },
            {
                type: 'button',
                label: __('Submit Request', 'nxp-easy-forms'),
            },
        ],
    },
    {
        id: 'real-estate-inquiry',
        name: __('Real Estate Inquiry', 'nxp-easy-forms'),
        description: __(
            'Collect property interests and viewing preferences',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'name',
                label: __('Your Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'tel',
                name: 'phone',
                label: __('Phone', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'select',
                name: 'contact_preference',
                label: __('Preferred Contact Method', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Email', 'nxp-easy-forms'),
                    __('Phone', 'nxp-easy-forms'),
                    __('Text Message', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'property_type',
                label: __('Property Type', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('House', 'nxp-easy-forms'),
                    __('Apartment', 'nxp-easy-forms'),
                    __('Condo', 'nxp-easy-forms'),
                    __('Land', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'budget_range',
                label: __('Budget Range', 'nxp-easy-forms'),
                required: false,
                options: [
                    __('Under $200k', 'nxp-easy-forms'),
                    __('$200k - $400k', 'nxp-easy-forms'),
                    __('$400k - $600k', 'nxp-easy-forms'),
                    __('Above $600k', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'checkbox',
                name: 'schedule_viewing',
                label: __(
                    "I'd like to schedule a property viewing",
                    'nxp-easy-forms'
                ),
                required: false,
            },
            {
                type: 'textarea',
                name: 'message',
                label: __('Additional Details', 'nxp-easy-forms'),
                required: false,
                placeholder: __(
                    'Share property interests or questions',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'button',
                label: __('Send Inquiry', 'nxp-easy-forms'),
            },
        ],
    },
    {
        id: 'course-registration',
        name: __('Course Registration', 'nxp-easy-forms'),
        description: __(
            'Enroll students in courses with experience level',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'student_name',
                label: __('Student Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email Address', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'date',
                name: 'birth_date',
                label: __('Date of Birth', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'select',
                name: 'course',
                label: __('Select Course', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Web Development Basics', 'nxp-easy-forms'),
                    __('Advanced JavaScript', 'nxp-easy-forms'),
                    __('UI/UX Design', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'radio',
                name: 'experience_level',
                label: __('Experience Level', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Beginner', 'nxp-easy-forms'),
                    __('Intermediate', 'nxp-easy-forms'),
                    __('Advanced', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'checkbox',
                name: 'terms',
                label: __(
                    "I agree to the <a href='#'>terms</a> and conditions",
                    'nxp-easy-forms'
                ),
                required: true,
            },
            {
                type: 'button',
                label: __('Register Now', 'nxp-easy-forms'),
            },
        ],
    },
    {
        id: 'event-registration',
        name: __('Event Registration', 'nxp-easy-forms'),
        description: __(
            'Collect attendee details and ticket choices',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'attendee_name',
                label: __('Full Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email Address', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'tel',
                name: 'phone',
                label: __('Phone Number', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'text',
                name: 'company',
                label: __('Company/Organization', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'select',
                name: 'ticket_type',
                label: __('Ticket Type', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('General Admission', 'nxp-easy-forms'),
                    __('VIP Pass', 'nxp-easy-forms'),
                    __('Student Discount', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'guests',
                label: __('Number of Guests', 'nxp-easy-forms'),
                required: true,
                options: ['1', '2', '3', '4', '5'],
            },
            {
                type: 'select',
                name: 'dietary_restrictions',
                label: __('Dietary Restrictions', 'nxp-easy-forms'),
                required: false,
                multiple: true,
                options: [
                    __('Vegetarian', 'nxp-easy-forms'),
                    __('Vegan', 'nxp-easy-forms'),
                    __('Gluten-free', 'nxp-easy-forms'),
                    __('Dairy-free', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'textarea',
                name: 'special_requirements',
                label: __('Special Requirements', 'nxp-easy-forms'),
                required: false,
                placeholder: __(
                    'Accessibility needs, allergies, etc.',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'button',
                label: __('Complete Registration', 'nxp-easy-forms'),
            },
        ],
    },
    {
        id: 'customer-feedback',
        name: __('Customer Feedback', 'nxp-easy-forms'),
        description: __(
            'Gather satisfaction scores and open comments',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'name',
                label: __('Your Name (Optional)', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email (Optional)', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'select',
                name: 'rating',
                label: __('Overall Satisfaction', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('5 - Excellent', 'nxp-easy-forms'),
                    __('4 - Good', 'nxp-easy-forms'),
                    __('3 - Average', 'nxp-easy-forms'),
                    __('2 - Below Average', 'nxp-easy-forms'),
                    __('1 - Poor', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'textarea',
                name: 'what_liked',
                label: __('What did you like most?', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'textarea',
                name: 'improvements',
                label: __('What could we improve?', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'checkbox',
                name: 'recommend',
                label: __(
                    'Would you recommend us to others?',
                    'nxp-easy-forms'
                ),
                required: false,
            },
            {
                type: 'button',
                label: __('Send Feedback', 'nxp-easy-forms'),
            },
        ],
    },
    {
        id: 'support-ticket',
        name: __('Support Ticket', 'nxp-easy-forms'),
        description: __(
            'Collect priority, category, and attachments',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'name',
                label: __('Your Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email Address', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'text',
                name: 'order_number',
                label: __('Order/Account Number', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'select',
                name: 'priority',
                label: __('Priority Level', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Low - General Question', 'nxp-easy-forms'),
                    __('Medium - Issue Affecting Work', 'nxp-easy-forms'),
                    __('High - Service Down', 'nxp-easy-forms'),
                    __('Critical - Complete Outage', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'category',
                label: __('Issue Category', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Technical Issue', 'nxp-easy-forms'),
                    __('Billing Question', 'nxp-easy-forms'),
                    __('Feature Request', 'nxp-easy-forms'),
                    __('Bug Report', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'textarea',
                name: 'issue_description',
                label: __('Describe the Issue', 'nxp-easy-forms'),
                required: true,
                placeholder: __(
                    'Please provide as much detail as possible...',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'file',
                name: 'screenshot',
                label: __(
                    'Attachment (Screenshot, logs, etc.)',
                    'nxp-easy-forms'
                ),
                required: false,
                accept: '.jpg,.png,.pdf,.txt,.log',
                maxFileSize: 10,
            },
            {
                type: 'button',
                label: __('Submit Ticket', 'nxp-easy-forms'),
            },
        ],
    },
    {
        id: 'post-submission',
        name: __('Front-end Article Submission', 'nxp-easy-forms'),
        description: __(
            'Collect Joomla articles from contributors for editorial review.',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'article_title',
                label: __('Article Title', 'nxp-easy-forms'),
                required: true,
                placeholder: __(
                    'Give your article a headline',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'textarea',
                name: 'article_introtext',
                label: __('Intro Text', 'nxp-easy-forms'),
                required: true,
                placeholder: __(
                    'Opening paragraph shown in blog listings (supports basic HTML).',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'textarea',
                name: 'article_fulltext',
                label: __('Full Article Content', 'nxp-easy-forms'),
                required: false,
                placeholder: __(
                    'Continue the story here. Leaving this blank will publish only the intro text.',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'file',
                name: 'article_featured_image',
                label: __('Featured Image (Optional)', 'nxp-easy-forms'),
                required: false,
                accept: 'image/jpeg,image/png,image/webp',
                maxFileSize: 5,
            },
            {
                type: 'select',
                name: 'article_category',
                label: __('Suggested Category', 'nxp-easy-forms'),
                required: false,
                options: [
                    __('Community News', 'nxp-easy-forms'),
                    __('Events', 'nxp-easy-forms'),
                    __('Guides & Tutorials', 'nxp-easy-forms'),
                    __('Opinion', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'text',
                name: 'article_tags',
                label: __('Tags (comma separated)', 'nxp-easy-forms'),
                required: false,
                placeholder: __('Separate tags with commas', 'nxp-easy-forms'),
            },
            {
                type: 'checkbox',
                name: 'content_guidelines',
                label: __(
                    'I confirm this submission follows the community guidelines.',
                    'nxp-easy-forms'
                ),
                required: true,
            },
            {
                type: 'button',
                label: __('Submit Article for Review', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                'Thanks for the submission! Our editors will review it shortly.',
                'nxp-easy-forms'
            ),
            integrations: {
                joomla_article: {
                    enabled: true,
                    category_id: 0,
                    status: 'unpublished',
                    author_mode: 'current_user',
                    fixed_author_id: 0,
                    language: '*',
                    access: 1,
                    map: {
                        title: 'article_title',
                        introtext: 'article_introtext',
                        fulltext: 'article_fulltext',
                        featured_image: 'article_featured_image',
                        tags: 'article_tags',
                        alias: 'article_alias',
                    },
                },
            },
        },
    },
    {
        id: 'quote-request',
        name: __('Request a Quote', 'nxp-easy-forms'),
        description: __(
            'Capture project requirements, timeline, and budget for B2B inquiries.',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'contact_name',
                label: __('Full Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'email',
                label: __('Business Email', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'tel',
                name: 'phone',
                label: __('Phone Number', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'text',
                name: 'company',
                label: __('Company Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'select',
                name: 'project_type',
                label: __('Project Type', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Website Design & Build', 'nxp-easy-forms'),
                    __('Application Development', 'nxp-easy-forms'),
                    __('Marketing Campaign', 'nxp-easy-forms'),
                    __('Consulting / Strategy', 'nxp-easy-forms'),
                    __('Other (describe below)', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'budget_range',
                label: __('Estimated Budget', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Under $5,000', 'nxp-easy-forms'),
                    __('$5,000 - $10,000', 'nxp-easy-forms'),
                    __('$10,000 - $25,000', 'nxp-easy-forms'),
                    __('$25,000+', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'timeline',
                label: __('Preferred Timeline', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Immediately', 'nxp-easy-forms'),
                    __('Within 1-3 months', 'nxp-easy-forms'),
                    __('Within 3-6 months', 'nxp-easy-forms'),
                    __('Flexible / Not sure', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'textarea',
                name: 'project_summary',
                label: __('Project Summary', 'nxp-easy-forms'),
                required: true,
                placeholder: __(
                    'Tell us about goals, audience, scope, and success criteria.',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'file',
                name: 'requirements_document',
                label: __('Upload Requirements (Optional)', 'nxp-easy-forms'),
                required: false,
                accept: 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                maxFileSize: 10,
            },
            {
                type: 'checkbox',
                name: 'consent_follow_up',
                label: __(
                    'I agree to be contacted with a tailored proposal.',
                    'nxp-easy-forms'
                ),
                required: true,
            },
            {
                type: 'button',
                label: __('Request My Quote', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                'Thanks! Our team will review the details and reach out with next steps.',
                'nxp-easy-forms'
            ),
        },
    },
    {
        id: 'rsvp-basic',
        name: __('RSVP Response', 'nxp-easy-forms'),
        description: __(
            'Quick attendance form with guest count and dietary notes.',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'attendee_name',
                label: __('Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'attendee_email',
                label: __('Email Address', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'radio',
                name: 'attending',
                label: __('Will you attend?', 'nxp-easy-forms'),
                required: true,
                options: [
                    __('Yes, I will be there', 'nxp-easy-forms'),
                    __("No, I can't make it", 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'guest_count',
                label: __('Number of Guests (including you)', 'nxp-easy-forms'),
                required: true,
                options: ['0', '1', '2', '3', '4', '5'],
            },
            {
                type: 'textarea',
                name: 'dietary_notes',
                label: __('Dietary Needs or Notes', 'nxp-easy-forms'),
                required: false,
                placeholder: __(
                    'Let us know about allergies or accessibility requirements.',
                    'nxp-easy-forms'
                ),
            },
            {
                type: 'button',
                label: __('Send RSVP', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                "Thanks for the update! We'll save your spot and be in touch with details.",
                'nxp-easy-forms'
            ),
        },
    },
    {
        id: 'volunteer-signup',
        name: __('Volunteer Signup', 'nxp-easy-forms'),
        description: __(
            'Gather availability, interests, and consent for background checks.',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'volunteer_name',
                label: __('Full Name', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'email',
                name: 'volunteer_email',
                label: __('Email Address', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'tel',
                name: 'volunteer_phone',
                label: __('Phone Number', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'select',
                name: 'availability',
                label: __('Availability', 'nxp-easy-forms'),
                required: true,
                multiple: true,
                options: [
                    __('Weekday Mornings', 'nxp-easy-forms'),
                    __('Weekday Afternoons', 'nxp-easy-forms'),
                    __('Weekday Evenings', 'nxp-easy-forms'),
                    __('Weekends', 'nxp-easy-forms'),
                    __('On-call for special events', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'select',
                name: 'interests',
                label: __('Volunteer Interests', 'nxp-easy-forms'),
                required: true,
                multiple: true,
                options: [
                    __('Event Support', 'nxp-easy-forms'),
                    __('Fundraising & Outreach', 'nxp-easy-forms'),
                    __('Administrative Help', 'nxp-easy-forms'),
                    __('Mentoring / Education', 'nxp-easy-forms'),
                    __('Community Service Projects', 'nxp-easy-forms'),
                ],
            },
            {
                type: 'textarea',
                name: 'skills',
                label: __('Relevant Skills or Experience', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'checkbox',
                name: 'background_check_consent',
                label: __(
                    'I consent to a background check if required.',
                    'nxp-easy-forms'
                ),
                required: true,
            },
            {
                type: 'select',
                name: 'tshirt_size',
                label: __('T-Shirt Size', 'nxp-easy-forms'),
                required: true,
                options: ['XS', 'S', 'M', 'L', 'XL', '2XL'],
            },
            {
                type: 'textarea',
                name: 'additional_notes',
                label: __('Anything else we should know?', 'nxp-easy-forms'),
                required: false,
            },
            {
                type: 'button',
                label: __('Sign Up to Volunteer', 'nxp-easy-forms'),
            },
        ],
        options: {
            success_message: __(
                'Thank you! Our volunteer coordinator will follow up with next steps soon.',
                'nxp-easy-forms'
            ),
        },
    },
    {
        id: 'user-registration',
        name: __('User Registration', 'nxp-easy-forms'),
        description: __(
            'Collect details to create a site account',
            'nxp-easy-forms'
        ),
        formType: 'user_registration',
        fields: [
            {
                type: 'text',
                name: 'username',
                label: __('Username', 'nxp-easy-forms'),
                required: true,
                placeholder: __('Choose a username', 'nxp-easy-forms'),
            },
            {
                type: 'email',
                name: 'email',
                label: __('Email Address', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'password',
                name: 'password',
                label: __('Password', 'nxp-easy-forms'),
                required: true,
                placeholder: __('Create a password', 'nxp-easy-forms'),
            },
            {
                type: 'checkbox',
                name: 'terms',
                label: __(
                    "I accept the <a href='#'>terms</a> of service",
                    'nxp-easy-forms'
                ),
                required: true,
            },
            {
                type: 'button',
                label: __('Create Account', 'nxp-easy-forms'),
            },
        ],
        options: {
            form_type: 'user_registration',
            send_email: false,
            store_submissions: false,
            success_message: __(
                'Registration successful! Please check your email to confirm your account.',
                'nxp-easy-forms'
            ),
            integrations: {
                user_registration: {
                    enabled: true,
                    user_group: 2,
                    require_activation: true,
                    send_activation_email: true,
                    auto_login: false,
                    field_mapping: {
                        username: 'username',
                        email: 'email',
                        password: 'password',
                        name: '',
                    },
                },
            },
        },
    },
    {
        id: 'user-login',
        name: __('User Login', 'nxp-easy-forms'),
        description: __(
            'Login form with username/email and password fields',
            'nxp-easy-forms'
        ),
        fields: [
            {
                type: 'text',
                name: 'username_or_email',
                label: __('Username or Email', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'password',
                name: 'password',
                label: __('Password', 'nxp-easy-forms'),
                required: true,
            },
            {
                type: 'button',
                label: __('Log In', 'nxp-easy-forms'),
            },
        ],
        options: {
            send_email: false,
            success_message: __('You are now logged in.', 'nxp-easy-forms'),
            integrations: {
                user_login: {
                    enabled: true,
                    identity_mode: 'auto',
                    remember_me: true,
                    redirect_url: '',
                    field_mapping: {
                        identity: 'username_or_email',
                        password: 'password',
                        twofactor: '',
                    },
                },
            },
        },
    },
];
