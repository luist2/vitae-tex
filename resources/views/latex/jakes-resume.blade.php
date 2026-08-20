%-------------------------
% VitaeTex — adaptación de Jake's Resume en español y A4.
%
% Autor original: Jake Gutierrez
% Basado en: https://github.com/sb2nov/resume
% Fuente: https://github.com/jakegut/resume
% Licencia: MIT; consulte THIRD_PARTY_NOTICES.md.
%------------------------

\documentclass[a4paper,11pt]{article}

\usepackage{latexsym}
\usepackage[empty]{fullpage}
\usepackage{titlesec}
\usepackage[usenames,dvipsnames]{color}
\usepackage{enumitem}
\usepackage[hidelinks]{hyperref}
\usepackage{fancyhdr}
\usepackage[spanish]{babel}
\usepackage{tabularx}

\pagestyle{fancy}
\fancyhf{}
\fancyfoot{}
\renewcommand{\headrulewidth}{0pt}
\renewcommand{\footrulewidth}{0pt}

\addtolength{\oddsidemargin}{-0.5in}
\addtolength{\evensidemargin}{-0.5in}
\addtolength{\textwidth}{1in}
\addtolength{\topmargin}{-0.5in}
\addtolength{\textheight}{1.0in}

\urlstyle{same}
\raggedbottom
\raggedright
\setlength{\tabcolsep}{0in}

\titleformat{\section}{
  \vspace{-4pt}\scshape\raggedright\large
}{}{0em}{}[\color{black}\titlerule \vspace{-5pt}]

% Tectonic utiliza XeTeX y fuentes OpenType con mapeo Unicode nativo.
% No deben añadirse glyphtounicode ni pdfgentounicode: son específicos de pdfTeX.

\newcommand{\resumeItem}[1]{
  \item\small{#1 \vspace{-2pt}}
}

\newcommand{\resumeSubheading}[4]{
  \vspace{-2pt}\item
    \begin{tabular*}{0.97\textwidth}[t]{l@{\extracolsep{\fill}}r}
      \textbf{#1} & #2 \\
      \textit{\small #3} & \textit{\small #4} \\
    \end{tabular*}\vspace{-7pt}
}

\newcommand{\resumeProjectHeading}[2]{
  \item
    \begin{tabular*}{0.97\textwidth}{l@{\extracolsep{\fill}}r}
      \small #1 & #2 \\
    \end{tabular*}\vspace{-7pt}
}

\newcommand{\resumeDescription}[1]{
  \item[]\small{#1 \vspace{-4pt}}
}

\renewcommand\labelitemii{$\vcenter{\hbox{\tiny$\bullet$}}$}
\newcommand{\resumeSubHeadingListStart}{\begin{itemize}[leftmargin=0.15in,label={}]}
\newcommand{\resumeSubHeadingListEnd}{\end{itemize}}
\newcommand{\resumeItemListStart}{\begin{itemize}}
\newcommand{\resumeItemListEnd}{\end{itemize}\vspace{-5pt}}

\begin{document}

\begin{center}
  \textbf{\Huge \scshape {!! $document['full_name'] !!}}@if ($document['header_details'] !== [] || $document['contacts'] !== []) \\ \vspace{1pt}@endif
@if ($document['header_details'] !== [])
  \small {!! implode(' $|$ ', $document['header_details']) !!}@if ($document['contacts'] !== []) \\ \vspace{1pt}@endif
@endif
@if ($document['contacts'] !== [])
  \small
@foreach ($document['contacts'] as $contact)
@if ($contact['destination'] !== null)
  \href{{!! $contact['destination'] !!}}{\underline{{!! $contact['label'] !!}}}
@else
  {!! $contact['label'] !!}
@endif
@if (! $loop->last)
  $|$
@endif
@endforeach
@endif
\end{center}

@foreach ($document['sections'] as $section)
@if ($section === 'professional_summary')
\section{Perfil profesional}
\small{{!! $document['professional_summary'] !!}}
@elseif ($section === 'education')
\section{Educación}
\resumeSubHeadingListStart
@foreach ($document['education_entries'] as $education)
  \resumeSubheading
    {{!! $education['institution'] !!}}{{!! $education['location'] ?? '' !!}}
    {{!! $education['qualification'] !!}}{{!! $education['dates'] !!}}
@if ($education['description'] !== null)
  \resumeDescription{{!! $education['description'] !!}}
@endif
@endforeach
\resumeSubHeadingListEnd
@elseif ($section === 'work_experience')
\section{Experiencia}
\resumeSubHeadingListStart
@foreach ($document['work_experiences'] as $experience)
  \resumeSubheading
    {{!! $experience['role'] !!}}{{!! $experience['dates'] !!}}
    {{!! $experience['employer'] !!}}{{!! $experience['location'] ?? '' !!}}
@if ($experience['highlights'] !== [])
  \resumeItemListStart
@foreach ($experience['highlights'] as $highlight)
    \resumeItem{{!! $highlight !!}}
@endforeach
  \resumeItemListEnd
@endif
@endforeach
\resumeSubHeadingListEnd
@elseif ($section === 'projects')
\section{Proyectos}
\resumeSubHeadingListStart
@foreach ($document['projects'] as $project)
  \resumeProjectHeading
    {
@if ($project['destination'] !== null)
      \href{{!! $project['destination'] !!}}{\textbf{{!! $project['name'] !!}}}
@else
      \textbf{{!! $project['name'] !!}}
@endif
@if ($project['role'] !== null)
      $|$ \emph{{!! $project['role'] !!}}
@endif
@if ($project['technologies'] !== [])
      $|$ \emph{{!! implode(', ', $project['technologies']) !!}}
@endif
    }
    {{!! $project['dates'] ?? '' !!}}
@if ($project['description'] !== null)
  \resumeDescription{{!! $project['description'] !!}}
@endif
@if ($project['highlights'] !== [])
  \resumeItemListStart
@foreach ($project['highlights'] as $highlight)
    \resumeItem{{!! $highlight !!}}
@endforeach
  \resumeItemListEnd
@endif
@endforeach
\resumeSubHeadingListEnd
@elseif ($section === 'skills')
\section{Habilidades técnicas}
\begin{itemize}[leftmargin=0.15in,label={}]
  \small{\item{
@foreach ($document['skill_groups'] as $group)
    \textbf{{!! $group['name'] !!}}: {!! implode(', ', $group['skills']) !!}
@if (! $loop->last)
    \\
@endif
@endforeach
  }}
\end{itemize}
@elseif ($section === 'certifications')
\section{Certificaciones}
\resumeSubHeadingListStart
@foreach ($document['certifications'] as $certification)
  \resumeSubheading
    {{!! $certification['name'] !!}}{{!! $certification['dates'] ?? '' !!}}
    {{!! $certification['issuer'] !!}}{
@if ($certification['credential'] !== null)
@if ($certification['credential']['destination'] !== null)
      \href{{!! $certification['credential']['destination'] !!}}{\underline{{!! $certification['credential']['label'] !!}}}
@else
      {!! $certification['credential']['label'] !!}
@endif
@endif
    }
@endforeach
\resumeSubHeadingListEnd
@endif
@endforeach

\end{document}
