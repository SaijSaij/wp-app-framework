<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * The blocks WordPress core registers (WordPress 7.1.3), to name them in
 * block references (`#[InnerBlocks(allowed)]`, `#[Block(parent, ancestor)]`,
 * `TemplateBlock`). A core block newer than this list is named by its full
 * name as a string; a string naming a block of this list is a build error.
 *
 * @api Enums
 */
enum CoreBlock: string
{
    case Accordion = 'core/accordion';
    case AccordionHeading = 'core/accordion-heading';
    case AccordionItem = 'core/accordion-item';
    case AccordionPanel = 'core/accordion-panel';
    case Archives = 'core/archives';
    case Audio = 'core/audio';
    case Avatar = 'core/avatar';
    case Block = 'core/block';
    case Breadcrumbs = 'core/breadcrumbs';
    case Button = 'core/button';
    case Buttons = 'core/buttons';
    case Calendar = 'core/calendar';
    case Categories = 'core/categories';
    case Code = 'core/code';
    case Column = 'core/column';
    case Columns = 'core/columns';
    case CommentAuthorName = 'core/comment-author-name';
    case CommentContent = 'core/comment-content';
    case CommentDate = 'core/comment-date';
    case CommentEditLink = 'core/comment-edit-link';
    case CommentReplyLink = 'core/comment-reply-link';
    case CommentTemplate = 'core/comment-template';
    case Comments = 'core/comments';
    case CommentsPagination = 'core/comments-pagination';
    case CommentsPaginationNext = 'core/comments-pagination-next';
    case CommentsPaginationNumbers = 'core/comments-pagination-numbers';
    case CommentsPaginationPrevious = 'core/comments-pagination-previous';
    case CommentsTitle = 'core/comments-title';
    case Cover = 'core/cover';
    case Details = 'core/details';
    case Embed = 'core/embed';
    case File = 'core/file';
    case Footnotes = 'core/footnotes';
    case Freeform = 'core/freeform';
    case Gallery = 'core/gallery';
    case Group = 'core/group';
    case Heading = 'core/heading';
    case HomeLink = 'core/home-link';
    case Html = 'core/html';
    case Icon = 'core/icon';
    case Image = 'core/image';
    case LatestComments = 'core/latest-comments';
    case LatestPosts = 'core/latest-posts';
    case LegacyWidget = 'core/legacy-widget';
    case List = 'core/list';
    case ListItem = 'core/list-item';
    case Loginout = 'core/loginout';
    case Math = 'core/math';
    case MediaText = 'core/media-text';
    case Missing = 'core/missing';
    case More = 'core/more';
    case Navigation = 'core/navigation';
    case NavigationLink = 'core/navigation-link';
    case NavigationOverlayClose = 'core/navigation-overlay-close';
    case NavigationSubmenu = 'core/navigation-submenu';
    case Nextpage = 'core/nextpage';
    case PageList = 'core/page-list';
    case PageListItem = 'core/page-list-item';
    case Paragraph = 'core/paragraph';
    case Pattern = 'core/pattern';
    case Playlist = 'core/playlist';
    case PlaylistTrack = 'core/playlist-track';
    case PostAuthor = 'core/post-author';
    case PostAuthorBiography = 'core/post-author-biography';
    case PostAuthorName = 'core/post-author-name';
    /** Legacy block of comments, registered by blocks/comments.php without a block directory. */
    case PostComments = 'core/post-comments';
    case PostCommentsCount = 'core/post-comments-count';
    case PostCommentsForm = 'core/post-comments-form';
    case PostCommentsLink = 'core/post-comments-link';
    case PostContent = 'core/post-content';
    case PostDate = 'core/post-date';
    case PostExcerpt = 'core/post-excerpt';
    case PostFeaturedImage = 'core/post-featured-image';
    case PostNavigationLink = 'core/post-navigation-link';
    case PostTemplate = 'core/post-template';
    case PostTerms = 'core/post-terms';
    case PostTimeToRead = 'core/post-time-to-read';
    case PostTitle = 'core/post-title';
    case Preformatted = 'core/preformatted';
    case Pullquote = 'core/pullquote';
    case Query = 'core/query';
    case QueryNoResults = 'core/query-no-results';
    case QueryPagination = 'core/query-pagination';
    case QueryPaginationNext = 'core/query-pagination-next';
    case QueryPaginationNumbers = 'core/query-pagination-numbers';
    case QueryPaginationPrevious = 'core/query-pagination-previous';
    case QueryTitle = 'core/query-title';
    case QueryTotal = 'core/query-total';
    case Quote = 'core/quote';
    case ReadMore = 'core/read-more';
    case Rss = 'core/rss';
    case Search = 'core/search';
    case Separator = 'core/separator';
    case Shortcode = 'core/shortcode';
    case SiteLogo = 'core/site-logo';
    case SiteTagline = 'core/site-tagline';
    case SiteTitle = 'core/site-title';
    case SocialLink = 'core/social-link';
    case SocialLinks = 'core/social-links';
    case Spacer = 'core/spacer';
    case TabList = 'core/tab-list';
    case TabPanel = 'core/tab-panel';
    case TabPanels = 'core/tab-panels';
    case Table = 'core/table';
    case Tabs = 'core/tabs';
    case TagCloud = 'core/tag-cloud';
    case TemplatePart = 'core/template-part';
    case TermCount = 'core/term-count';
    case TermDescription = 'core/term-description';
    case TermName = 'core/term-name';
    case TermTemplate = 'core/term-template';
    case TermsQuery = 'core/terms-query';
    case TextColumns = 'core/text-columns';
    case Verse = 'core/verse';
    case Video = 'core/video';
    case WidgetGroup = 'core/widget-group';
}
