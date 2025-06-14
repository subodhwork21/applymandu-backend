<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $user->jobseekerProfile->first_name ?? '' }} {{ $user->jobseekerProfile->middle_name ?? '' }}
        {{ $user->jobseekerProfile->last_name ?? '' }} - Resume</title>
    <style>
        :root {
            --primary-color: rgb(6, 49, 92);
            --secondary-color: #3498db;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --border-color: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            color: var(--text-color);
            line-height: 1.5;
            background-color: #f9f9f9;
            padding: 0;
            margin: 0;
        }

        /* A4 paper dimensions: 210mm × 297mm */
        .container {
            width: 100%;
            height: 297mm;
            /* height: 100vh; */
            margin: 0 auto;
            display: table;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        @media print {
            .container {
                page-break-inside: avoid;
            }
        }

        @page {
            margin: 0;
        }

        .page-break {
            page-break-after: always;
        }

        .main-content {
            display: table-cell;
            width: 66%;
            padding: 20mm 15mm 0mm 15mm;
            vertical-align: top;
        }

        .sidebar {
            display: table-cell;
            width: 33%;
            background-color: var(--primary-color);
            color: white;
            padding: 20mm 8mm 20mm 8mm;
            vertical-align: top;
        }

        h1 {
            font-size: 20pt;
            margin-bottom: 12px;
            color: #000;
        }

        h2 {
            font-size: 12pt;
            color: var(--secondary-color);
            margin: 16px 0 8px 0;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 6px;
            font-weight: 600;
        }

        .sidebar h2 {
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding-bottom: 8px;
            margin-top: 20px;
            font-size: 11pt;
        }

        p {
            margin-bottom: 8px;
            font-size: 9pt;
        }

        ul {
            padding-left: 16px;
            margin: 8px 0;
        }

        li {
            margin-bottom: 4px;
            font-size: 9pt;
        }

        .entry-title,
        .degree {
            font-weight: bold;
            color: #333;
            font-size: 10pt;
        }

        .entry-subtitle,
        .institution {
            font-weight: normal;
            font-style: italic;
            font-size: 9pt;
        }

        .entry-date,
        .date {
            color: #666;
            font-size: 8pt;
            margin: 4px 0;
        }

        .contact-item {
            margin-bottom: 8px;
            font-size: 9pt;

        }

        .sidebar a {
            color: white;
            text-decoration: none;
        }

        .sidebar a:hover {
            text-decoration: underline;
        }

        .skills-list {
            list-style-type: none;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .skills-list li {
            margin-bottom: 6px;
            font-size: 9pt;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
        }

        .icon {
            font-weight: bold;
            display: inline-block;
            width: 60px;
            margin-right: 5px;
        }

        .skill-tag {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 8pt;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }

        .entry-header {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }

        .entry-header .entry-title {
            display: table-cell;
            text-align: left;
        }

        .entry-header .entry-date {
            display: table-cell;
            text-align: right;
        }

        .entry-content {
            margin-top: 4px;
        }

        .entry {
            margin-bottom: 12px;
        }

        .education,
        .project {
            margin-bottom: 12px;
        }

        .reference-item {
            margin-bottom: 12px;
            font-size: 9pt;
        }

        .language-item {
            font-size: 9pt;
            margin-bottom: 4px;
        }

        .section {
            margin-bottom: 15px;
        }

        .references {
            margin-top: 8px;
            padding-bottom: 0px;
        }

        .skills {
            margin-top: 8px;
        }

        .languages {
            margin-top: 8px;
        }

        .project-links {
            margin-top: 4px;
            font-size: 9pt;
        }

        .project-links a {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .project-links a:hover {
            text-decoration: underline;
        }
    </style>

</head>

<body>

    <div class="container">
        <div class="main-content">
            <h1>{{ $user->jobseekerProfile->first_name ?? '' }} {{ $user->jobseekerProfile->middle_name ?? '' }}
                {{ $user->jobseekerProfile->last_name ?? '' }}</h1>

            @if (isset($user->jobseekerProfile->career_objectives) && !empty($user->jobseekerProfile->career_objectives))
                <div class="section">
                    <h2>Career Objective</h2>
                    <p>{{ $user->jobseekerProfile->career_objectives }}</p>
                </div>
            @endif

            @if (isset($user->experiences) && count($user->experiences ?? []) > 0)
                <div class="section">
                    <h2>Professional Experience</h2>

                    @foreach ($user->experiences as $experience)
                        <div class="entry">
                            <div class="entry-header">
                                <span class="entry-title">{{ $experience->position_title ?? 'Position' }}</span>
                                <span class="entry-date">
                                    @if (isset($experience->start_date))
                                        {{ \Carbon\Carbon::parse($experience->start_date)->format('M Y') }} -
                                        @if (isset($experience->currently_work_here) && $experience->currently_work_here)
                                            Present
                                        @elseif(isset($experience->end_date))
                                            {{ \Carbon\Carbon::parse($experience->end_date)->format('M Y') }}
                                        @else
                                            Present
                                        @endif
                                    @endif
                                </span>
                            </div>
                            <div class="entry-subtitle">{{ $experience->company_name ?? '' }} @if (isset($experience->job_level) && !empty($experience->job_level))
                                    | {{ $experience->job_level }}
                                @endif
                            </div>
                            @if (isset($experience->roles_and_responsibilities) && !empty($experience->roles_and_responsibilities))
                                <div class="entry-content">
                                    <p>{{ $experience->roles_and_responsibilities }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if (isset($user->educations) && count($user->educations ?? []) > 0)
                <div class="section">
                    <h2>Education</h2>

                    @foreach ($user->educations as $education)
                        <div class="entry">
                            <div class="entry-header">
                                <span class="entry-title">
                                    {{ $education->degree ?? '' }}
                                    @if (isset($education->subject_major) && !empty($education->subject_major))
                                        in {{ $education->subject_major }}
                                    @endif
                                </span>
                                <span class="entry-date">
                                    @if (isset($education->joined_year))
                                        {{ \Carbon\Carbon::parse($education->joined_year)->format('M Y') }} -
                                        @if (isset($education->currently_studying) && $education->currently_studying)
                                            Present
                                        @elseif(isset($education->passed_year))
                                            {{ \Carbon\Carbon::parse($education->passed_year)->format('M Y') }}
                                        @else
                                            Present
                                        @endif
                                    @endif
                                </span>
                            </div>
                            <div class="entry-subtitle">
                                {{ $education->institution ?? '' }}
                                @if (isset($education->university_board) && !empty($education->university_board))
                                    , {{ $education->university_board }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (isset($user->certificates) && count($user->certificates ?? []) > 0)
                <div class="section">
                    <h2>Certifications</h2>
                    @foreach ($user->certificates as $certificate)
                        <div class="entry">
                            <div class="entry-header">
                                <span class="entry-title">{{ $certificate->title ?? 'Certificate' }}</span>
                                @if (isset($certificate->year) && !empty($certificate->year))
                                    <span class="entry-date">{{ $certificate->year }}</span>
                                @endif
                            </div>
                            @if (isset($certificate->issuer) && !empty($certificate->issuer))
                                <div class="entry-subtitle">{{ $certificate->issuer }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if (isset($user->trainings) && count($user->trainings ?? []) > 0)
                <div class="section">
                    <h2>Training</h2>
                    @foreach ($user->trainings as $training)
                        <div class="entry">
                            <div class="entry-title">{{ $training->title ?? 'Training' }}</div>
                            @if (isset($training->institution) && !empty($training->institution))
                                <div class="entry-subtitle">{{ $training->institution }}</div>
                            @endif
                            @if (isset($training->description) && !empty($training->description))
                                <div class="entry-content">
                                    <p>{{ $training->description }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if (isset($user->projects) && count($user->projects ?? []) > 0)
                <div class="section">
                    <h2>Projects</h2>
                    @foreach ($user->projects as $project)
                        <div class="entry">
                            <div class="entry-title">{{ $project->title ?? 'Project' }}</div>
                            @if (isset($project->description) && !empty($project->description))
                                <div class="entry-content">
                                    <p>{{ $project->description }}</p>
                                </div>
                            @endif
                            @if (isset($project->links) && !empty($project->links))
                                <div class="project-links">
                                    <a href="{{ $project->links }}" target="_blank">Project Link</a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif(isset($user->collegeProjects) && count($user->collegeProjects ?? []) > 0)
                <div class="section">
                    <h2>College Projects</h2>
                    @foreach ($user->collegeProjects as $project)
                        <div class="entry">
                            <div class="entry-title">{{ $project->title ?? 'Project' }}</div>
                            @if (isset($project->description) && !empty($project->description))
                                <div class="entry-content">
                                    <p>{{ $project->description }}</p>
                                </div>
                            @endif
                            @if (isset($project->link) && !empty($project->link))
                                <div class="project-links">
                                    <a href="{{ $project->link }}" target="_blank">Project Link</a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if (isset($user->references) && count($user->references ?? []) > 0)
                <div class="section">
                    <h2>References</h2>
                    <div class="references">
                        @foreach ($user->references as $reference)
                            <div class="reference-item">
                                <strong>{{ $reference->name ?? 'Reference' }}</strong>
                                @if (isset($reference->position) || isset($reference->company))
                                    - {{ $reference->position ?? '' }}
                                    @if (isset($reference->company) && !empty($reference->company))
                                        , {{ $reference->company }}
                                    @endif
                                @endif
                                <br>
                                @if (isset($reference->email) && !empty($reference->email))
                                    Email: {{ $reference->email }}
                                @endif
                                @if (isset($reference->phone) && !empty($reference->phone))
                                    | Phone: {{ $reference->phone }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="sidebar">
            <h2>Details</h2>
            @if (isset($user->jobseekerProfile))
                <div class="contact-item">
                    {{ $user->jobseekerProfile->city_tole ?? '' }}
                    @if (isset($user->jobseekerProfile->municipality) && !empty($user->jobseekerProfile->municipality))
                        , {{ $user->jobseekerProfile->municipality }}
                    @endif
                    @if (isset($user->jobseekerProfile->district) && !empty($user->jobseekerProfile->district))
                        , {{ $user->jobseekerProfile->district }}
                    @endif
                </div>
                @if (isset($user->jobseekerProfile->mobile) && !empty($user->jobseekerProfile->mobile))
                    <div class="contact-item">
                        {{ $user->jobseekerProfile->mobile }}
                    </div>
                @endif
            @endif

            @if (isset($user->email) && !empty($user->email))
                <div class="contact-item">
                    <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                </div>
            @endif

            @if (isset($user->social_links) && count($user->social_links ?? []) > 0)
                <h2>Links</h2>
                @foreach ($user->social_links as $link)
                    @if (isset($link->url) && !empty($link->url))
                        <div class="contact-item">
                            <span class="icon">Web:</span>
                            <a href="{{ $link->url }}" target="_blank">
                                @php
                                    try {
                                        $url = $link->url;
                                        $domain = parse_url($url, PHP_URL_HOST);
                                        $domain = $domain ? str_replace('www.', '', $domain) : $url;
                                    } catch (Exception $e) {
                                        $domain = $url ?? 'Link';
                                    }
                                @endphp
                                {{ $domain }}
                            </a>
                        </div>
                    @endif
                @endforeach
            @endif




            @if (isset($user->skills) && count($user->skills ?? []) > 0)
                <h2>Skills</h2>
                <div class="skills">
                    @foreach ($user->skills as $skill)
                        @if (isset($skill->name) && !empty($skill->name))
                            <span class="skill-tag">{{ $skill->name }}</span>
                        @endif
                    @endforeach
                </div>
            @endif

            @if (isset($user->languages) && count($user->languages ?? []) > 0)
                <h2>Languages</h2>
                <div class="languages">
                    @foreach ($user->languages as $language)
                        <div class="language-item">
                            <strong>{{ $language->language ?? 'Language' }}:</strong>
                            {{ $language->proficiency ?? 'Proficient' }}
                        </div>
                    @endforeach
                </div>
            @endif

            @if (isset($user->jobseekerProfile))
                <h2>Additional Info</h2>
                <ul style="list-style-position: inside; padding-left: 0;">
                    @if (isset($user->jobseekerProfile->industry) && !empty($user->jobseekerProfile->industry))
                        <li>Industry: {{ $user->jobseekerProfile->industry }}</li>
                    @endif
                    @if (isset($user->jobseekerProfile->preferred_job_type) && !empty($user->jobseekerProfile->preferred_job_type))
                        <li>Job Type: {{ $user->jobseekerProfile->preferred_job_type }}</li>
                    @endif
                    @if (isset($user->jobseekerProfile->gender) && !empty($user->jobseekerProfile->gender))
                        <li>Gender: {{ $user->jobseekerProfile->gender }}</li>
                    @endif
                    @if (isset($user->jobseekerProfile->date_of_birth))
                        <li>DOB: {{ \Carbon\Carbon::parse($user->jobseekerProfile->date_of_birth)->format('F d, Y') }}
                        </li>
                    @endif
                    @if (isset($user->jobseekerProfile->has_driving_license))
                        <li>Driving License: {{ $user->jobseekerProfile->has_driving_license ? 'Yes' : 'No' }}</li>
                    @endif
                    @if (isset($user->jobseekerProfile->has_vehicle) && $user->jobseekerProfile->has_vehicle)
                        <li>Has Vehicle: Yes</li>
                    @endif
                    @if (isset($user->jobseekerProfile->looking_for) && !empty($user->jobseekerProfile->looking_for))
                        <li>Looking For: {{ $user->jobseekerProfile->looking_for }}</li>
                    @endif
                    @if (isset($user->jobseekerProfile->salary_expectations) && !empty($user->jobseekerProfile->salary_expectations))
                        <li>Salary Expectations: {{ $user->jobseekerProfile->salary_expectations }}</li>
                    @endif
                </ul>
            @endif
        </div>
    </div>
</body>

</html>
