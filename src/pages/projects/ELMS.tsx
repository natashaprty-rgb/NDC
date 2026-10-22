import { CreditCard, LayoutDashboard, ShieldCheck, Users } from 'lucide-react';
import ProjectLayout from '../../components/project/ProjectLayout';
import ProjectHeader from '../../components/project/ProjectHeader';
import ProjectSection from '../../components/project/ProjectSection';
import ProjectOverview from '../../components/project/ProjectOverview';
import TechStack from '../../components/project/TechStack';
import TechnicalHighlights from '../../components/project/TechnicalHighlights';
import { ElmsIcon } from '../../assets/project_icons';
import { socialLinks } from '../../config/socialLinks';

const ELMS = () => {
  const features = [
    {
      icon: ShieldCheck,
      title: "Secure Authentication",
      description: "Role-based login system protecting student and admin accounts"
    },
    {
      icon: LayoutDashboard,
      title: "Admin Dashboard",
      description: "Central dashboard for managing courses, users, and content"
    },
    {
      icon: CreditCard,
      title: "Integrated Payments",
      description: "Built-in payment features for course enrollment"
    },
    {
      icon: Users,
      title: "Student Management",
      description: "Tools to track enrollment, progress, and records"
    }
  ];

  const overviewParagraphs = [
    "ELMS is a web-based E-Learning Management System built to give schools and training providers a simple way to manage online courses. It combines secure authentication, an admin dashboard, and integrated payment features into a single platform.",
    "The system was designed with both administrators and students in mind — administrators can manage courses and content through a dedicated dashboard, while students get a straightforward way to enroll, pay, and access their coursework."
  ];

  const technologies = ["PHP", "MySQL", "JavaScript", "HTML", "CSS"];

  const highlights = [
    "Built a secure authentication system with role-based access for admins and students",
    "Designed an admin dashboard for managing courses, users, and platform content",
    "Integrated payment handling for course enrollment",
    "Structured the database in MySQL to support courses, users, and transactions",
    "Built entirely with core PHP, JavaScript, HTML, and CSS"
  ];

  return (
    <ProjectLayout>
      <ProjectHeader
        icon={ElmsIcon}
        title="ELMS - E-Learning Management System"
        subtitle="A secure, full-featured platform for managing online courses, students, and payments"
        githubUrl={socialLinks.repositories.projectOne}
        features={features}
      />

      <ProjectSection title="Overview">
        <ProjectOverview paragraphs={overviewParagraphs} />
      </ProjectSection>

      <ProjectSection title="Tech Stack">
        <TechStack technologies={technologies} />
      </ProjectSection>

      <ProjectSection title="Technical Highlights">
        <TechnicalHighlights highlights={highlights} />
      </ProjectSection>
    </ProjectLayout>
  );
};

export default ELMS;
