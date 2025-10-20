<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:exsl="http://exslt.org/common"
                xmlns:xs="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                version="1.0"
                extension-element-prefixes="exsl"
                xmlns="http://www.openarchives.org/OAI/2.0/">

    <xsl:import href="abandon_continue_publication_process_button.xsl"/>
    
    <!-- Key for grouping subjects by language -->
    <xsl:key name="subjects-by-lang" match="metadata/oai_dc:dc/dc:subject[@xml:lang]" use="@xml:lang"/>

    <xsl:output method="html" encoding="utf-8" indent="yes"/>

    <xsl:template match="record">
        <xsl:variable name="prefixUrl" select="episciences/prefixUrl/text()"/>
        <xsl:variable name="rightOrcid" select="episciences/rightOrcid/text()"/>
        <xsl:if test="$rightOrcid = '1'">
            <!-- Modal -->
            <form id="post-orcid-author" method="POST">
                <xsl:attribute name="action">
                    <xsl:value-of select="concat($prefixUrl,'paper/postorcidauthor')"/>
                </xsl:attribute>
                <div class="modal fade" id="author-modal-orcid" tabindex="-1" role="dialog" aria-labelledby="author-modal-orcid-label" aria-hidden="true">
                    <div class="modal-dialog modal-orcid" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="author-modal-orcid-label-title">  <xsl:value-of
                                        select="php:function('Ccsd_Tools::translate', 'Ajouter les ORCID aux auteurs')"/></h5>
                            </div>
                            <div id="modal-body-authors" class="modal-body">
                                <input class='hidden' id='modal-called' value='0' />
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-success" id="valid-new-orcid">Save changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="hidden" id="paperid-for-author"><xsl:value-of select="episciences/paperId"/></div>
            <div class="hidden" id="docid-for-author"><xsl:value-of select="episciences/id"/></div>
        </xsl:if>

        <xsl:variable name="client_language" select="php:function('Episciences_Tools::getLocale')"/>
        <xsl:variable name="doc_language" select="metadata/oai_dc:dc/dc:language"/>
        <xsl:variable name="title">
            <xsl:choose>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $client_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $client_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $doc_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $doc_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang]"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title"/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>

        <div class="panel panel-default collapsable" style="margin-top: 20px">

            <div class="panel-heading">
                <h2 class="panel-title" style="margin-bottom: 5px">

                    <span class="darkgrey">
                        <xsl:for-each select="metadata/oai_dc:dc/dc:creator[position() &lt;= 5]">
                            <xsl:value-of select="php:function('Episciences_Tools::reformatOaiDcAuthor', string(.))"/>
                            <xsl:if test="position() != last()"> ; </xsl:if>
                        </xsl:for-each>
                        <xsl:if test="count(metadata/oai_dc:dc/dc:creator) &gt; 5">
                            <i> et al.</i>
                        </xsl:if>
                        -
                    </span>

                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($title))"/>


                </h2>

                <xsl:if test="episciences/paperId and episciences/paperId != ''">
                    <xsl:value-of select="episciences/review_code"/>:<xsl:value-of select="episciences/paperId"/> -
                </xsl:if>

                <xsl:value-of select="episciences/review"/>
                <xsl:if test="episciences/status = 16 and episciences/publication_date">,
                    <xsl:value-of
                            select="php:function('Episciences_View_Helper_Date::Date', string(episciences/publication_date))"/>
                </xsl:if>
                <xsl:if test="episciences/volume and episciences/volume != ''">,
                    <xsl:value-of select="episciences/volumeName"/>
                </xsl:if>

                <xsl:if test="episciences/doi and episciences/doi != ''">
                    -
                    <a rel="noopener" target="_blank">
                        <xsl:attribute name="href">
                            <xsl:text>https://doi.org/</xsl:text><xsl:value-of select="episciences/doi"/>
                        </xsl:attribute>
                        https://doi.org/<xsl:value-of select="episciences/doi"/>
                    </a>
                </xsl:if>
            </div>

            <div class="panel-body in">

                <strong>
                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($title))"/>
                </strong>

                <span class="label label-default pull-right"><xsl:value-of select="php:function('Ccsd_Tools::translate',string(episciences/submissionType))"/></span>

                <p>
                    <i>
                        <div id="paper-authors">
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Auteurs : ')"/>
                            <xsl:choose>
                                <xsl:when test="episciences/authorEnriched!= '' ">
                                    <xsl:value-of select="episciences/authorEnriched" disable-output-escaping="yes"/>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:for-each select="metadata/oai_dc:dc/dc:creator">
                                        <xsl:value-of select="php:function('Episciences_Tools::reformatOaiDcAuthor', string(.))"/>
                                        <xsl:if test="position() != last()"> ; </xsl:if>
                                    </xsl:for-each>
                                </xsl:otherwise>
                            </xsl:choose>
                        </div>
                        <div id="orcid-author-existing" class="hidden">
                            <xsl:value-of select="episciences/authorsOrcid"/>
                        </div>
                        <div id="authors-list" class="hidden">
                            <xsl:value-of select="episciences/listAuthors"/>
                        </div>

                    </i>
                </p>
                <xsl:value-of select="episciences/listAffi" disable-output-escaping="yes"/>
                <xsl:call-template name="process-descriptions"/>

                <hr/>



                <div class="paper-actions" style="margin-bottom: 10px;">
                    <xsl:if test="episciences">
                        <xsl:choose>
                            <xsl:when test="episciences/tmp/text() = '1'">
                                <xsl:variable name="docUrls"
                                              select="php:function('Episciences_Tools::buildHtmlTmpDocUrls', episciences/id)"/>
                                <xsl:value-of select=" $docUrls" disable-output-escaping="yes"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <a target="_blank">
                                    <xsl:attribute name="href">
                                        <xsl:choose>
                                            <xsl:when test="episciences/status = 16">
                                                <xsl:value-of select="concat($prefixUrl, episciences/id, '/pdf')"/>
                                            </xsl:when>
                                            <xsl:otherwise>
                                                <xsl:value-of select="episciences/paperURL"/>
                                            </xsl:otherwise>
                                        </xsl:choose>
                                    </xsl:attribute>
                                    <xsl:if test="episciences/notHasHook/text() = '1'">
                                        <button class="btn btn-primary btn" style="margin-right: 5px">
                                            <span class="fas fa-file-download" style="margin-right: 5px"/>
                                            <xsl:value-of select="php:function('Ccsd_Tools::translate', &quot;Télécharger l'article&quot;)"/>
                                        </button>
                                    </xsl:if>
                                </a>

                                <xsl:if test="episciences/docURL != episciences/paperURL">
                                    <a rel="noopener" target="_blank">
                                        <xsl:attribute name="href">
                                            <xsl:value-of select="episciences/docURL"/>
                                        </xsl:attribute>
                                        <button class="btn btn-default btn-sm">
                                            <span class="fas fa-external-link-alt" style="margin-right: 5px"/>
                                            <xsl:value-of select="episciences/docUrlBtnLabel"/>
                                        </button>
                                    </a>
                                </xsl:if>
                            </xsl:otherwise>
                        </xsl:choose>
                    </xsl:if>
                </div>

                <xsl:if test="episciences/doi and episciences/doi != ''">
                    <div class="paper-doi small">
                        <a rel="noopener" target="_blank">
                            <xsl:attribute name="href">
                                <xsl:text>https://doi.org/</xsl:text><xsl:value-of select="episciences/doi"/>
                            </xsl:attribute>
                            https://doi.org/<xsl:value-of select="episciences/doi"/>
                        </a>
                    </div>
                </xsl:if>

                <xsl:if test="episciences/identifier and episciences/identifier != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Source : ')"/>
                        <xsl:if test="episciences/docURL != episciences/paperURL">
                            <a target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:value-of select="episciences/docURL"/>
                                </xsl:attribute>
                                <xsl:value-of select="episciences/identifier"/>
                            </a>
                        </xsl:if>
                    </div>
                </xsl:if>

                <xsl:if test="episciences/volume and episciences/volume != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Volume : ')"/>
                        <xsl:value-of select="episciences/volumeName"/>
                    </div>
                </xsl:if>

                <xsl:if test="episciences/section and episciences/section != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Rubrique : ')"/>
                        <xsl:value-of select="episciences/sectionName"/>
                    </div>
                </xsl:if>

                <xsl:choose>
                    <xsl:when test="episciences/status = 16 and episciences/publication_date">
                        <div class="small">
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Publié le : ')"/>
                            <xsl:value-of
                                    select="php:function('Episciences_View_Helper_Date::Date', string(episciences/publication_date))"/>
                        </div>
                    </xsl:when>
                </xsl:choose>

                <xsl:choose>
                    <xsl:when test="episciences/submission_date and episciences/submission_date != '' and episciences/isImported/text() = '1'">
                        <div class="small">
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Importé le : ')"/>
                            <xsl:value-of select="php:function('Episciences_View_Helper_Date::Date', string(episciences/submission_date))"/>
                        </div>
                    </xsl:when>
                    <xsl:otherwise>

                        <xsl:if test="episciences/acceptance_date/text()">
                            <div class="small">
                                <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Accepté le : ')"/>
                                <xsl:value-of select="php:function('Episciences_View_Helper_Date::Date', string(episciences/acceptance_date))"/>
                            </div>
                        </xsl:if>

                        <xsl:if test="episciences/submission_date/text()">
                            <div class="small">
                                <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Soumis le : ')"/>
                                <xsl:value-of select="php:function('Episciences_View_Helper_Date::Date', string(episciences/submission_date))"/>
                            </div>
                        </xsl:if>
                    </xsl:otherwise>
                </xsl:choose>

                <xsl:if test="metadata/oai_dc:dc/dc:subject/text()">
                    <div class="small force-word-wrap">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Mots-clés : ')"/>
                        <xsl:call-template name="process-subjects"/>
                    </div>
                </xsl:if>

                <!-- licenses -->

                <xsl:choose>
                    <xsl:when test="episciences/paperLicence/text() != ''">
                        <div class="small">
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Licence : ')"/>
                            <a rel="noopener" target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:value-of select="episciences/paperLicence/text()"/>
                                </xsl:attribute>
                                <xsl:value-of
                                        select="php:function('Ccsd_Tools::translate', string(episciences/paperLicence))"/>
                            </a>
                        </div>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:for-each select="metadata/oai_dc:dc/dc:rights">
                            <xsl:variable name="doc_rights" select="."/>
                            <xsl:if test="not (contains($doc_rights, 'info:eu-repo/semantics/'))">
                                <div class="small">
                                    <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Licence : ')"/>
                                    <a rel="noopener" target="_blank">
                                        <xsl:if test="contains($doc_rights, 'href=') and not(contains($doc_rights, '[CC_NO]'))">
                                            <xsl:attribute name="href">
                                            <xsl:value-of select="$doc_rights"/>
                                            </xsl:attribute>
                                        </xsl:if>
                                        <xsl:value-of select="php:function('Ccsd_Tools::translate', string($doc_rights))"/>
                                    </a>
                                </div>
                            </xsl:if>
                        </xsl:for-each>
                    </xsl:otherwise>
                </xsl:choose>

                <xsl:if test="episciences/funding/text() != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Financement : ')"/>
                        <xsl:value-of select="episciences/funding" disable-output-escaping="yes"/>
                    </div>
                </xsl:if>

                <!-- Only show HR if there are buttons to display -->
                <xsl:if test="((episciences/status = '') and (episciences/uid = php:function('Episciences_Auth::getUid')) and (episciences/hasOtherVersions = '')) or (episciences and not(episciences/tmp/text() = '1') and (($rightOrcid = '1') or (episciences/isAllowedToListAssignedPapers/text() = '1'))) or (episciences/uid = php:function('Episciences_Auth::getUid'))">
                    <hr />
                </xsl:if>
                <xsl:if test="(episciences/status = '') and (episciences/uid = php:function('Episciences_Auth::getUid') and episciences/hasOtherVersions = '')">
                    <a>
                        <xsl:attribute name="href">
                            <xsl:value-of select="concat($prefixUrl,'paper/remove/id/', episciences/id)"/>
                        </xsl:attribute>
                        <button class="btn btn-danger btn-sm" style="margin-right: 5px">
                            <span class="glyphicon glyphicon-remove-circle" style="margin-right: 5px"/>
                            <xsl:variable name="string">
                                <xsl:text>Supprimer la soumission</xsl:text>
                            </xsl:variable>
                            <xsl:value-of
                                    select="php:function('Ccsd_Tools::translate', $string)"/>
                        </button>
                    </a>
                </xsl:if>

                <xsl:if test="episciences">
                    <div id='record-loading' style="display:none"/>
                    <xsl:if test="not(episciences/tmp/text() = '1')">
                        <xsl:if test="$rightOrcid = '1'">
                            <button id="update_orcid_author" class="btn btn-default btn-sm" style="margin-left: 5px" data-toggle="modal" data-target="#author-modal-orcid">
                                <xsl:attribute name="onclick">
                                    <xsl:value-of select="'updateOrcidAuthors()'"/>
                                </xsl:attribute>
                                <span class="fab fa-orcid" style="margin-right: 5px"/>
                                <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Mettre à jour les ORCID')"/>
                            </button>
                            <div id="rightOrcid" style="display:none;">
                                <xsl:value-of select="$rightOrcid"/>
                            </div>
                        </xsl:if>
                    <xsl:if test="episciences/isAllowedToListAssignedPapers/text() = '1'">
                        <button id="update_metadata" class="btn btn-default btn-sm" style="margin-left: 5px">
                            <xsl:attribute name="onclick">
                                <xsl:value-of select="concat('updateMetaData(this, ', episciences/id,')')"/>
                            </xsl:attribute>
                            <span class="fas fa-sync-alt" style="margin-right: 5px"/>
                            <xsl:value-of
                                    select="php:function('Ccsd_Tools::translate', 'Mettre à jour les métadonnées')"/>
                        </button>
                        </xsl:if>
                    </xsl:if>

                    <!-- Abandon/resume publication process -->
                    <xsl:if test="episciences/uid = php:function('Episciences_Auth::getUid')">
                        <xsl:apply-imports/>
                    </xsl:if>
                </xsl:if>
            </div>

        </div>

    </xsl:template>

    <!-- Template for processing descriptions with conditional language prefixes -->
    <xsl:template name="process-descriptions">
        <xsl:param name="justify" select="'false'"/>
        
        <!-- Count only displayable descriptions (excluding 'International audience') -->
        <xsl:variable name="displayable_desc_count" select="count(metadata/oai_dc:dc/dc:description[normalize-space(.) != 'International audience'])"/>
        
        <xsl:for-each select="metadata/oai_dc:dc/dc:description">
            <!-- Skip descriptions with value 'International audience' -->
            <xsl:if test="normalize-space(.) != 'International audience'">
                <xsl:choose>
                    <xsl:when test="$justify = 'true'">
                        <p class="small force-word-wrap" style="text-align: justify">
                            <!-- Add lang attribute if language is specified -->
                            <xsl:if test="@xml:lang">
                                <xsl:attribute name="lang">
                                    <xsl:value-of select="@xml:lang"/>
                                </xsl:attribute>
                                <!-- Add dir attribute for RTL languages -->
                                <xsl:if test="php:function('Episciences_Tools::isRtlLanguage', string(@xml:lang))">
                                    <xsl:attribute name="dir">rtl</xsl:attribute>
                                </xsl:if>
                            </xsl:if>
                            <!-- Only add language prefix if multiple descriptions AND this one has xml:lang -->
                            <xsl:if test="$displayable_desc_count > 1 and @xml:lang">
                                <strong>[<xsl:value-of select="@xml:lang"/>] </strong>
                            </xsl:if>
                            <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string(.), true())" disable-output-escaping="yes"/>
                        </p>
                    </xsl:when>
                    <xsl:otherwise>
                        <p class="small force-word-wrap" style="">
                            <!-- Add lang attribute if language is specified -->
                            <xsl:if test="@xml:lang">
                                <xsl:attribute name="lang">
                                    <xsl:value-of select="@xml:lang"/>
                                </xsl:attribute>
                                <!-- Add dir attribute for RTL languages -->
                                <xsl:if test="php:function('Episciences_Tools::isRtlLanguage', string(@xml:lang))">
                                    <xsl:attribute name="dir">rtl</xsl:attribute>
                                </xsl:if>
                            </xsl:if>
                            <!-- Only add language prefix if multiple descriptions AND this one has xml:lang -->
                            <xsl:if test="$displayable_desc_count > 1 and @xml:lang">
                                <strong>[<xsl:value-of select="@xml:lang"/>] </strong>
                            </xsl:if>
                            <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string(.), true())" disable-output-escaping="yes"/>
                        </p>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:if>
        </xsl:for-each>
    </xsl:template>

    <!-- Template for processing subjects with language grouping -->
    <xsl:template name="process-subjects">
        <!-- Count total subjects -->
        <xsl:variable name="subject_count" select="count(metadata/oai_dc:dc/dc:subject)"/>
        
        <!-- Only add language prefixes if there are multiple subjects -->
        <xsl:choose>
            <xsl:when test="$subject_count > 1">
                <!-- Group subjects by language -->
                
                <!-- First, display subjects without language -->
                <xsl:variable name="subjects_no_lang" select="metadata/oai_dc:dc/dc:subject[not(@xml:lang)]"/>
                <xsl:if test="$subjects_no_lang">
                    <xsl:for-each select="$subjects_no_lang">
                        <xsl:value-of select="."/>
                        <xsl:if test="position() != last()">, </xsl:if>
                    </xsl:for-each>
                    <xsl:if test="metadata/oai_dc:dc/dc:subject[@xml:lang]">, </xsl:if>
                </xsl:if>
                
                <!-- Then, group by language -->
                <xsl:for-each select="metadata/oai_dc:dc/dc:subject[@xml:lang][generate-id() = generate-id(key('subjects-by-lang', @xml:lang)[1])]">
                    <xsl:sort select="@xml:lang"/>
                    <xsl:variable name="current_lang" select="@xml:lang"/>
                    
                    <strong>[<xsl:value-of select="$current_lang"/>] </strong>
                    
                    <xsl:for-each select="key('subjects-by-lang', $current_lang)">
                        <xsl:value-of select="."/>
                        <xsl:if test="position() != last()">, </xsl:if>
                    </xsl:for-each>
                    
                    <xsl:if test="position() != last()">; </xsl:if>
                </xsl:for-each>
            </xsl:when>
            <xsl:otherwise>
                <!-- Single subject: no language prefix, just comma-separated -->
                <xsl:for-each select="metadata/oai_dc:dc/dc:subject">
                    <xsl:value-of select="."/>
                    <xsl:if test="position() != last()">, </xsl:if>
                </xsl:for-each>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

</xsl:stylesheet>
