import { ChartColumn, ClipboardList, Database, UsersRound } from 'lucide-react';
import ProjectLayout from '../../components/project/ProjectLayout';
import ProjectHeader from '../../components/project/ProjectHeader';
import ProjectSection from '../../components/project/ProjectSection';
import ProjectOverview from '../../components/project/ProjectOverview';
import TechStack from '../../components/project/TechStack';
import TechnicalHighlights from '../../components/project/TechnicalHighlights';
import { SurveyIcon } from '../../assets/project_icons';
import { socialLinks } from '../../config/socialLinks';

const SurveyManagementSystem = () => {
  const features = [
    {
      icon: ClipboardList,
      title: "Survey Collection",
      description: "Structured forms for gathering survey responses"
    },
    {
      icon: Database,
      title: "Centralized Data Store",
      description: "MongoDB-backed storage for all collected survey data"
    },
    {
      icon: UsersRound,
      title: "Team Built",
      description: "Developed collaboratively as part of a team project"
    },
    {
      icon: ChartColumn,
      title: "Data Management",
      description: "Tools for organizing and reviewing collected responses"
    }
  ];

  const overviewParagraphs = [
    "The Survey Management System was built as a team project to enable structured data collection and management. It provides a clean way to create surveys, gather responses, and manage the resulting data.",
    "Working within a team, I contributed to building the system using Node.js and Express for the backend, MongoDB for data storage, and HTML/CSS for the interface."
  ];

  const technologies = ["Node.js", "Express", "MongoDB", "HTML", "CSS"];

  const highlights = [
    "Collaborated within a team to design and build a full data collection workflow",
    "Built RESTful endpoints with Node.js and Express to handle survey data",
    "Modeled and stored survey responses in MongoDB",
    "Structured the interface with HTML and CSS for straightforward data entry"
  ];

  return (
    <ProjectLayout>
      <ProjectHeader
        icon={SurveyIcon}
        title="Survey Management System"
        subtitle="A collaborative system for collecting, storing, and managing survey data"
        githubUrl={socialLinks.repositories.projectTwo}
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

export default SurveyManagementSystem;
